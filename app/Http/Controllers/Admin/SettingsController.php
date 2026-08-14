<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteMedia;
use App\Notifications\EmailOtpNotification;
use App\Services\AuditService;
use App\Services\CatalogSyncService;
use App\Jobs\RepriceSmsVirtualCatalog;
use App\Services\MailSettingsConfigurator;
use App\Services\ProviderBalanceService;
use App\Services\PayKitaClient;
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
    public function index(Request $request, Settings $settings): View
    {
        $tab = trim((string) $request->query('tab', ''));

        // URL lama tetap diarahkan ke satu halaman penyedia pembayaran.
        if (in_array($tab, ['pakasir', 'duitku'], true)) {
            $tab = 'payments';
        }

        $tabs = [
            'site',
            'auth',
            'orders',
            'pricing',
            'topup',
            'payments',
            'sms_virtual',
            'mail',
            'security',
        ];

        if ($tab !== '' && ! in_array($tab, $tabs, true)) {
            $tab = '';
        }

        $values = $tab === 'payments' ? $settings->group('paykita') : ($tab !== '' ? $settings->group($tab) : []);

        return view('admin.settings.index', compact('tab', 'values'));
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
                    'paykita',
                    'sms_virtual',
                    'mail',
                    'security',
                ]),
            ],
        ])['group'];

        $data = $request->validate($this->rules($group));

        if ($group === 'orders') {
            $data['refund_on_expired'] = $request->boolean('refund_on_expired');
        }

        $logoUrl = null;
        $seoImageUrl = null;
        $logoUrlInput = null;
        $seoImageUrlInput = null;
        $seoImageMeta = null;

        if ($group === 'site') {
            $logoUrlInput = trim((string) ($data['logo_url'] ?? ''));
            $seoImageUrlInput = trim((string) ($data['seo_image_url'] ?? ''));

            if ($request->hasFile('logo_image')) {
                $logoUrl = $this->storeSiteImage(
                    $request,
                    'logo_image',
                    'business_logo',
                    '/media/business-logo',
                );
            }

            if ($request->hasFile('seo_image')) {
                $seoFile = $request->file('seo_image');
                $seoImageMeta = @getimagesize($seoFile->getRealPath()) ?: null;

                $seoImageUrl = $this->storeSiteImage(
                    $request,
                    'seo_image',
                    'meta_seo',
                    '/og-image.jpg',
                );
            }

            // URL aktif ditangani terpisah supaya input kosong tidak
            // menghapus file lokal yang sudah aktif. Jika file dan URL
            // dikirim bersamaan, file lokal diprioritaskan.
            unset(
                $data['logo_url'],
                $data['logo_image'],
                $data['seo_image_url'],
                $data['seo_image'],
            );
        }

        $mapped = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['api_key', 'password', 'provider_webhook_secret'], true)
                && ($value === null || $value === '')) {
                continue;
            }

            $mapped[$group.'.'.$key] = $value;
        }

        if ($group === 'site') {
            if ($logoUrl !== null) {
                $mapped['site.logo_url'] = $logoUrl;
            } elseif ($logoUrlInput !== '') {
                $mapped['site.logo_url'] = $logoUrlInput;
            }

            if ($seoImageUrl !== null) {
                $mapped['site.seo_image_url'] = $seoImageUrl;
                $mapped['site.seo_image_width'] = (string) ((int) ($seoImageMeta[0] ?? 0));
                $mapped['site.seo_image_height'] = (string) ((int) ($seoImageMeta[1] ?? 0));
                $mapped['site.seo_image_mime'] = (string) ($seoImageMeta['mime'] ?? $request->file('seo_image')?->getMimeType() ?? 'image/jpeg');
            } elseif ($seoImageUrlInput !== '') {
                $mapped['site.seo_image_url'] = $seoImageUrlInput;
                // Dimensi URL eksternal tidak dapat dipercaya tanpa mengunduh URL dari server.
                // Kosongkan structured metadata lama agar tidak mengiklankan ukuran yang salah.
                $mapped['site.seo_image_width'] = '';
                $mapped['site.seo_image_height'] = '';
                $mapped['site.seo_image_mime'] = '';
            }
        }

        $settings->setMany($mapped);

        if (in_array($group, ['pricing', 'sms_virtual'], true)) {
            // Repricing seluruh katalog dapat menyentuh ratusan/ribuan baris.
            // Jalankan lewat worker Redis agar request Simpan kembali seketika.
            RepriceSmsVirtualCatalog::dispatch();
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

        $message = in_array($group, ['pricing', 'sms_virtual'], true)
            ? 'Pengaturan berhasil disimpan. Harga layanan sedang diperbarui di background.'
            : 'Pengaturan berhasil disimpan dan langsung diterapkan.';

        return back()->with('success', $message);
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

    public function testPayKita(PayKitaClient $client): RedirectResponse
    {
        try {
            $result = $client->probe();
            return back()->with('success', $result['message'] ?? 'Koneksi PayKita berhasil.');
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
                'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'logo_zoom' => ['nullable', 'integer', 'min:100', 'max:400'],
                'logo_mobile_shift' => ['nullable', 'integer', 'min:-5', 'max:30'],
                'seo_title' => ['nullable', 'string', 'max:70'],
                'seo_description' => ['nullable', 'string', 'max:180'],
                'seo_keywords' => ['nullable', 'string', 'max:500'],
                'seo_hashtags' => ['nullable', 'string', 'max:500'],
                'seo_image_url' => ['nullable', 'url', 'max:2048'],
                'seo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
            'paykita' => [
                'base_url' => ['required', 'url'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'ttl_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
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

    private function storeSiteImage(
        Request $request,
        string $field,
        string $key,
        string $publicPath,
    ): string {
        $file = $request->file($field);
        if (! $file) {
            throw new \RuntimeException('File gambar tidak ditemukan.');
        }

        $binary = file_get_contents($file->getRealPath());
        if ($binary === false || $binary === '') {
            throw new \RuntimeException('File gambar tidak dapat dibaca.');
        }

        SiteMedia::query()->updateOrCreate(
            ['key' => $key],
            [
                'mime_type' => $file->getMimeType() ?: 'image/png',
                'data_base64' => base64_encode($binary),
            ],
        );

        Cache::forget('site-media:'.$key);

        // Media lokal disajikan lewat route publik sehingga tidak memerlukan
        // symbolic-link storage dan tetap aman untuk deployment read-only.
        // Versi query memaksa browser/crawler mengambil file terbaru.
        return $publicPath.'?v='.now()->timestamp;
    }
}
