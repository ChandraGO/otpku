<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSmsVirtualCatalog;
use App\Notifications\EmailOtpNotification;
use App\Services\AuditService;
use App\Services\CatalogSyncService;
use App\Services\PakasirClient;
use App\Services\SmsVirtualClient;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(Request $request, Settings $settings): View
    {
        $tab = $request->string('tab', 'site')->toString();
        if (! in_array($tab, ['site', 'auth', 'orders', 'pricing', 'topup', 'sms_virtual', 'pakasir', 'mail', 'security'], true)) $tab = 'site';
        return view('admin.settings.index', ['tab' => $tab, 'values' => $settings->group($tab)]);
    }
    public function update(Request $request, Settings $settings, AuditService $audit): RedirectResponse
    {
        $group = $request->validate(['group' => ['required', Rule::in(['site', 'auth', 'orders', 'pricing', 'topup', 'sms_virtual', 'pakasir', 'mail', 'security'])]])['group'];
        $rules = $this->rules($group);
        $data = $request->validate($rules);
        if ($group === 'orders') $data['refund_on_expired'] = $request->boolean('refund_on_expired');
        $mapped = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['api_key', 'password', 'provider_webhook_secret'], true) && ($value === null || $value === '')) continue;
            $mapped[$group.'.'.$key] = $value;
        }
        $settings->setMany($mapped);
        if ($group === 'pricing') app(CatalogSyncService::class)->reprice();
        $audit->record('settings.update', 'settings', [], ['group' => $group, 'keys' => array_keys($mapped)]);
        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
    public function testSms(SmsVirtualClient $client): RedirectResponse
    {
        try { $balance = $client->balance(); return back()->with('success', 'Koneksi SMS Virtual berhasil. Balance: '.json_encode($balance['balance'] ?? $balance['data']['balance'] ?? $balance['data'] ?? '-')); }
        catch (Throwable $e) { return back()->withErrors(['settings' => $e->getMessage()]); }
    }
    public function testPakasir(PakasirClient $client): RedirectResponse
    {
        try { $client->project(); return back()->with('success', 'Konfigurasi Pakasir terbaca. Detail transaksi akan diverifikasi saat invoice dibuat.'); }
        catch (Throwable $e) { return back()->withErrors(['settings' => $e->getMessage()]); }
    }
    public function testMail(Request $request): RedirectResponse
    {
        try {
            Notification::route('mail', $request->user()->email)->notify(new EmailOtpNotification('123456', 'verify_email', 10));
            return back()->with('success', 'Email uji dimasukkan ke antrean pengiriman.');
        } catch (Throwable $e) { return back()->withErrors(['settings' => $e->getMessage()]); }
    }
    public function syncCatalog(): RedirectResponse
    {
        SyncSmsVirtualCatalog::dispatch();
        return back()->with('success', 'Sinkronisasi katalog dimasukkan ke antrean.');
    }
    private function rules(string $group): array
    {
        return match ($group) {
            'site' => ['name' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:500'], 'support_whatsapp' => ['nullable', 'string', 'max:30']],
            'auth' => ['email_otp_expiry_minutes' => ['required', 'integer', 'min:3', 'max:60'], 'email_otp_resend_seconds' => ['required', 'integer', 'min:30', 'max:600']],
            'orders' => ['default_expiry_minutes' => ['required', 'integer', 'min:5', 'max:180'], 'refund_on_expired' => ['nullable', 'boolean']],
            'pricing' => ['markup_percent' => ['required', 'numeric', 'min:0', 'max:500'], 'fixed_fee' => ['required', 'numeric', 'min:0', 'max:1000000'], 'round_to' => ['required', 'integer', Rule::in([1, 10, 100, 500, 1000])]],
            'topup' => ['minimum' => ['required', 'integer', 'min:1000'], 'maximum' => ['required', 'integer', 'gt:minimum'], 'payment_method' => ['required', 'string', 'max:40']],
            'sms_virtual' => ['base_url' => ['required', 'url'], 'api_key' => ['nullable', 'string', 'max:500'], 'timeout' => ['required', 'integer', 'min:5', 'max:120']],
            'pakasir' => ['base_url' => ['required', 'url'], 'project' => ['required', 'string', 'max:100'], 'api_key' => ['nullable', 'string', 'max:500'], 'payment_method' => ['required', 'string', 'max:40']],
            'mail' => ['mailer' => ['required', Rule::in(['smtp', 'log'])], 'host' => ['required', 'string'], 'port' => ['required', 'integer'], 'username' => ['nullable', 'string'], 'password' => ['nullable', 'string'], 'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])], 'from_address' => ['required', 'email'], 'from_name' => ['required', 'string']],
            'security' => ['provider_webhook_secret' => ['nullable', 'string', 'min:24']],
        };
    }
}
