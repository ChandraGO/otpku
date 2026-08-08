<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\EmailOtpNotification;
use App\Services\AuditService;
use App\Services\CatalogSyncService;
use App\Services\DuitkuClient;
use App\Services\MailSettingsConfigurator;
use App\Services\PakasirClient;
use App\Services\PaymentGatewayManager;
use App\Services\ProviderBalanceService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(Request $request, Settings $settings, PaymentGatewayManager $gateways): View
    {
        $tab = $request->string('tab', 'site')->toString();
        $tabs = [
            'site',
            'auth',
            'orders',
            'pricing',
            'topup',
            'payments',
            'sms_virtual',
            'pakasir',
            'duitku',
            'mail',
            'security',
        ];

        if (! in_array($tab, $tabs, true)) {
            $tab = 'site';
        }

        return view('admin.settings.index', [
            'tab' => $tab,
            'values' => $settings->group($tab),
            'activeGateway' => $gateways->activeGateway(),
            'pendingGateway' => $gateways->pendingGateway(),
            'gatewayBlockers' => $gateways->blockingCounts(),
            'duitkuMethods' => $gateways->duitkuMethodOptions(),
        ]);
    }

    public function update(
        Request $request,
        Settings $settings,
        AuditService $audit,
        MailSettingsConfigurator $mailConfigurator,
    ): RedirectResponse {
        $group = $request->validate([
            'group' => [
                'required',
                Rule::in([
                    'site',
                    'auth',
                    'orders',
                    'pricing',
                    'topup',
                    'sms_virtual',
                    'pakasir',
                    'duitku',
                    'mail',
                    'security',
                ]),
            ],
        ])['group'];

        $data = $request->validate($this->rules($group));

        if ($group === 'orders') {
            $data['refund_on_expired'] = $request->boolean('refund_on_expired');
        }

        $mapped = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['api_key', 'password', 'provider_webhook_secret'], true)
                && ($value === null || $value === '')) {
                continue;
            }

            $mapped[$group.'.'.$key] = $value;
        }

        $settings->setMany($mapped);

        if (in_array($group, ['pricing', 'sms_virtual'], true)) {
            app(CatalogSyncService::class)->reprice();
        }

        if ($group === 'mail') {
            $mailConfigurator->configure(true);
        }

        if ($group === 'sms_virtual') {
            Cache::forget('sms-virtual:provider-balance:v2');
            Cache::forget('sms-virtual:provider-balance:value');
        }

        $audit->record('settings.update', 'settings', [], [
            'group' => $group,
            'keys' => array_keys($mapped),
        ]);

        return back()->with(
            'success',
            'Pengaturan berhasil disimpan dan langsung diterapkan.',
        );
    }

    public function testSms(ProviderBalanceService $providerBalance): RedirectResponse
    {
        $balance = $providerBalance->get(refresh: true);

        if (! $balance['available']) {
            return back()->withErrors([
                'settings' => $balance['error'] ?: 'Saldo provider tidak dapat dimuat.',
            ]);
        }

        $formatted = 'Rp '.number_format((float) $balance['idr'], 0, ',', '.');
        $suffix = $balance['source'] === 'provider'
            ? ' (live dari provider).'
            : ' (fallback saldo terakhir; koneksi live sedang bermasalah).';

        return back()->with(
            'success',
            'Koneksi SMS Virtual berhasil. Saldo provider '.$formatted.$suffix,
        );
    }

    public function testPakasir(PakasirClient $client): RedirectResponse
    {
        try {
            $configuration = $client->assertConfigured();

            return back()->with(
                'success',
                'Konfigurasi Pakasir siap: '.$configuration['base_url'].' · project '.$configuration['project'].'. Tes transaksi sebenarnya dilakukan saat invoice dibuat.',
            );
        } catch (Throwable $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }
    }

    public function testDuitku(DuitkuClient $client, Settings $settings): RedirectResponse
    {
        try {
            $configuration = $client->assertConfigured();
            $amount = max(10000, (int) $settings->get('topup.minimum', 10000));
            $methods = $client->paymentMethods($amount);
            $configured = strtoupper((string) $configuration['payment_method']);
            $active = collect($methods)->contains(fn ($item) => is_array($item)
                && strtoupper((string) ($item['paymentMethod'] ?? '')) === $configured);

            return back()->with(
                'success',
                'Koneksi Duitku berhasil ('.$configuration['environment'].'). Metode aktif terdeteksi: '.count($methods).'. Metode bawaan '.$configured.($active ? ' tersedia.' : ' belum aktif pada proyek merchant.'),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }
    }

    public function switchPaymentGateway(
        Request $request,
        PaymentGatewayManager $gateways,
        PakasirClient $pakasir,
        DuitkuClient $duitku,
        AuditService $audit,
        Settings $settings,
    ): RedirectResponse {
        $target = $request->validate([
            'active_gateway' => ['required', Rule::in(['pakasir', 'duitku'])],
        ])['active_gateway'];

        try {
            if ($target === 'duitku') {
                $configuration = $duitku->assertConfigured();
                $amount = max(10000, (int) $settings->get('topup.minimum', 10000));
                $methods = $duitku->paymentMethods($amount);
                $configuredMethod = strtoupper((string) $configuration['payment_method']);
                $methodActive = collect($methods)->contains(fn ($item) => is_array($item)
                    && strtoupper((string) ($item['paymentMethod'] ?? '')) === $configuredMethod);

                if (! $methodActive) {
                    throw new \RuntimeException('Metode Duitku '.$configuredMethod.' belum aktif pada proyek merchant. Pilih channel yang aktif lalu coba lagi.');
                }
            } else {
                $pakasir->assertConfigured();
            }

            $result = $gateways->requestSwitch($target, $request->user());
            $audit->record('payment_gateway.switch', 'payment_gateway', [], $result);

            if ($result['state'] === 'scheduled') {
                return back()->with(
                    'success',
                    'Peralihan ke '.$gateways->label($target).' dijadwalkan. Gateway lama tetap aktif sampai '.(int) $result['blockers']['topups'].' isi saldo dan '.(int) $result['blockers']['orders'].' pesanan aktif selesai.',
                );
            }

            if ($result['state'] === 'unchanged') {
                return back()->with('success', $gateways->label($target).' tetap menjadi penyedia pembayaran aktif. Jadwal peralihan sebelumnya dibatalkan.');
            }

            return back()->with('success', 'Penyedia pembayaran aktif sekarang: '.$gateways->label($target).'.');
        } catch (Throwable $e) {
            return back()->withErrors(['settings' => $e->getMessage()]);
        }
    }

    public function testMail(
        Request $request,
        MailSettingsConfigurator $mailConfigurator,
    ): RedirectResponse {
        try {
            $mailConfigurator->configure(true);

            Notification::route('mail', $request->user()->email)
                ->notifyNow(new EmailOtpNotification('123456', 'verify_email', 10));

            return back()->with(
                'success',
                'Email uji berhasil dikirim langsung ke '.$request->user()->email.'.',
            );
        } catch (Throwable $e) {
            return back()->withErrors([
                'settings' => 'Email uji gagal: '.$e->getMessage(),
            ]);
        }
    }

    public function syncCatalog(CatalogSyncService $service): RedirectResponse
    {
        try {
            $result = $service->sync();

            return back()->with(
                'success',
                sprintf(
                    'Sinkronisasi selesai: %d negara, %d layanan, %d harga, %d harga tersedia.',
                    (int) ($result['countryCount'] ?? 0),
                    (int) ($result['serviceCount'] ?? 0),
                    (int) ($result['priceCount'] ?? 0),
                    (int) ($result['availablePriceCount'] ?? 0),
                ),
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'settings' => 'Sinkronisasi katalog gagal: '.$e->getMessage(),
            ]);
        }
    }

    private function rules(string $group): array
    {
        return match ($group) {
            'site' => [
                'name' => ['required', 'string', 'max:100'],
                'description' => ['required', 'string', 'max:500'],
                'support_whatsapp' => ['nullable', 'string', 'max:30'],
                'logo_url' => ['nullable', 'url', 'max:2048'],
            ],
            'auth' => [
                'email_otp_expiry_minutes' => ['required', 'integer', 'min:3', 'max:60'],
                'email_otp_resend_seconds' => ['required', 'integer', 'min:30', 'max:600'],
            ],
            'orders' => [
                'default_expiry_minutes' => ['required', 'integer', 'min:5', 'max:180'],
                'refund_on_expired' => ['nullable', 'boolean'],
            ],
            'pricing' => [
                'markup_percent' => ['required', 'numeric', 'min:0', 'max:500'],
                'fixed_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
                'round_to' => ['required', 'integer', Rule::in([1, 10, 100, 500, 1000])],
            ],
            'topup' => [
                'minimum' => ['required', 'integer', 'min:1000'],
                'maximum' => ['required', 'integer', 'min:10000', 'gt:minimum'],
            ],
            'sms_virtual' => [
                'base_url' => ['required', 'url'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
                'low_balance_threshold' => ['required', 'numeric', 'min:0', 'max:1000000000000'],
                'reserve_buffer_percent' => ['required', 'numeric', 'min:0', 'max:500'],
            ],
            'pakasir' => [
                'base_url' => ['required', 'url'],
                'project' => ['required', 'string', 'max:100'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'payment_method' => ['required', 'string', 'max:40'],
            ],
            'duitku' => [
                'environment' => ['required', Rule::in(['sandbox', 'production'])],
                'merchant_code' => ['required', 'string', 'max:100'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'payment_method' => ['required', Rule::in(array_keys(app(PaymentGatewayManager::class)->duitkuMethodOptions()))],
                'expiry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            ],
            'mail' => [
                'mailer' => ['required', Rule::in(['smtp', 'log'])],
                'host' => ['required', 'string'],
                'port' => ['required', 'integer'],
                'username' => ['nullable', 'string'],
                'password' => ['nullable', 'string'],
                'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
                'from_address' => ['required', 'email'],
                'from_name' => ['required', 'string'],
            ],
            'security' => [
                'provider_webhook_secret' => ['nullable', 'string', 'min:24'],
            ],
        };
    }
}
