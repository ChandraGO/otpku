<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->where('role', 'user')
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($x) => $x->where('username', 'ilike', $term)->orWhere('email', 'ilike', $term)->orWhere('whatsapp', 'ilike', $term));
            })->latest()->paginate(25)->withQueryString();
        return view('admin.users.index', compact('users'));
    }
    public function show(User $user): View
    {
        return view('admin.users.show', ['user' => $user, 'transactions' => $user->walletTransactions()->latest()->limit(30)->get(), 'orders' => $user->otpOrders()->latest()->limit(20)->get()]);
    }
    public function status(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        abort_if($user->isAdmin(), 422);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended', 'banned'])]]);
        $before = $user->only('status'); $user->update($data); $audit->record('user.status', $user, $before, $data);
        return back()->with('success', 'Status pengguna diperbarui.');
    }
    public function adjustBalance(Request $request, User $user, WalletService $wallet, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['credit', 'debit'])], 'amount' => ['required', 'numeric', 'min:1', 'max:100000000'], 'note' => ['required', 'string', 'max:255']]);
        $key = 'admin-adjustment:'.str()->uuid();
        $tx = $data['direction'] === 'credit'
            ? $wallet->credit($user, (float) $data['amount'], 'admin_adjustment', $key, $data['note'], User::class, (string) $user->id)
            : $wallet->debit($user, (float) $data['amount'], 'admin_adjustment', $key, $data['note'], User::class, (string) $user->id);
        $audit->record('user.balance_adjustment', $user, [], $tx->toArray());
        return back()->with('success', 'Saldo pengguna berhasil disesuaikan.');
    }
}
