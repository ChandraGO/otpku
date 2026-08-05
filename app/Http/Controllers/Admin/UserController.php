<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->where('role', 'user')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($nested) => $nested
                    ->where('username', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term)
                    ->orWhere('whatsapp', 'ilike', $term));
            })
            ->when($request->boolean('deletion_pending'), fn ($query) => $query->where('deletion_request_status', 'pending'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
            'transactions' => $user->walletTransactions()->latest()->limit(30)->get(),
            'orders' => $user->otpOrders()->latest()->limit(20)->get(),
        ]);
    }

    public function status(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_if($user->isAdmin(), 422);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'banned'])]]);
        $before = $user->only('status');
        $user->update($data);
        $audit->record('user.status', $user, $before, $data);

        return back()->with('success', 'Status pengguna berhasil diperbarui.');
    }

    public function adjustBalance(Request $request, User $user, WalletService $wallet, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'note' => ['required', 'string', 'max:255'],
        ]);
        $key = 'admin-adjustment:'.str()->uuid();
        $tx = $data['direction'] === 'credit'
            ? $wallet->credit($user, (float) $data['amount'], 'admin_adjustment', $key, $data['note'], User::class, (string) $user->id)
            : $wallet->debit($user, (float) $data['amount'], 'admin_adjustment', $key, $data['note'], User::class, (string) $user->id);
        $audit->record('user.balance_adjustment', $user, [], $tx->toArray());

        return back()->with('success', 'Saldo pengguna berhasil disesuaikan.');
    }

    public function reviewDeletionRequest(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_if($user->isAdmin(), 422);
        abort_unless($user->deletion_request_status === 'pending', 422, 'Tidak ada permintaan penghapusan yang menunggu.');

        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $before = $user->only(['status', 'deletion_request_status', 'api_key_hash']);

        DB::transaction(function () use ($user, $data): void {
            if ($data['decision'] === 'approve') {
                $user->forceFill([
                    'status' => 'suspended',
                    'api_key' => null,
                    'api_key_hash' => null,
                    'api_key_created_at' => null,
                    'deletion_request_status' => 'approved',
                    'deletion_reviewed_at' => now(),
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();
                return;
            }

            $user->update([
                'deletion_request_status' => 'rejected',
                'deletion_reviewed_at' => now(),
            ]);
        }, 3);

        $audit->record('user.deletion_request_review', $user, $before, [
            'decision' => $data['decision'],
            'status' => $user->fresh()->status,
            'deletion_request_status' => $user->fresh()->deletion_request_status,
        ]);

        return back()->with(
            'success',
            $data['decision'] === 'approve'
                ? 'Permintaan disetujui. Akun dinonaktifkan, sesi dihapus, dan API key dicabut.'
                : 'Permintaan penghapusan ditolak. Akun tetap aktif.',
        );
    }
}
