<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports', [
            'summary' => [
                'users' => User::query()->where('role', 'user')->count(),
                'wallet_total' => User::query()->sum('balance'),
                'topup_total' => Topup::query()->where('status', 'completed')->sum('amount'),
                'sales_total' => OtpOrder::query()->whereNotIn('status', ['failed', 'refunded'])->sum('sell_price'),
                'provider_cost' => OtpOrder::query()->whereNotIn('status', ['failed', 'refunded'])->sum('provider_cost'),
            ],
        ]);
    }
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['users', 'orders', 'topups', 'wallet'], true), 404);
        return response()->streamDownload(function () use ($type): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            match ($type) {
                'users' => $this->users($out), 'orders' => $this->orders($out), 'topups' => $this->topups($out), 'wallet' => $this->wallet($out),
            };
            fclose($out);
        }, $type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
    private function users($out): void
    {
        fputcsv($out, ['ID', 'Username', 'Nama', 'Email', 'WhatsApp', 'Status', 'Saldo', 'Dibuat']);
        User::query()->where('role', 'user')->orderBy('id')->chunk(500, fn ($rows) => $rows->each(fn ($u) => fputcsv($out, [$u->id, $u->username, $u->name, $u->email, $u->whatsapp, $u->status, $u->balance, $u->created_at])));
    }
    private function orders($out): void
    {
        fputcsv($out, ['ID', 'User', 'Layanan', 'Negara', 'Nomor', 'Status', 'Harga', 'Modal', 'Activation ID', 'Dibuat']);
        OtpOrder::query()->with('user')->orderBy('created_at')->chunk(500, fn ($rows) => $rows->each(fn ($o) => fputcsv($out, [$o->id, $o->user?->email, $o->service_name, $o->country_name, $o->phone_number, $o->status, $o->sell_price, $o->provider_cost, $o->provider_activation_id, $o->created_at])));
    }
    private function topups($out): void
    {
        fputcsv($out, ['Order ID', 'User', 'Nominal', 'Total Bayar', 'Metode', 'Status', 'Dibuat', 'Dibayar']);
        Topup::query()->with('user')->orderBy('created_at')->chunk(500, fn ($rows) => $rows->each(fn ($t) => fputcsv($out, [$t->order_id, $t->user?->email, $t->amount, $t->total_payment, $t->payment_method, $t->status, $t->created_at, $t->paid_at])));
    }
    private function wallet($out): void
    {
        fputcsv($out, ['ID', 'User', 'Arah', 'Kategori', 'Nominal', 'Saldo Sebelum', 'Saldo Sesudah', 'Keterangan', 'Dibuat']);
        WalletTransaction::query()->with('user')->orderBy('created_at')->chunk(500, fn ($rows) => $rows->each(fn ($t) => fputcsv($out, [$t->id, $t->user?->email, $t->direction, $t->category, $t->amount, $t->balance_before, $t->balance_after, $t->description, $t->created_at])));
    }
}
