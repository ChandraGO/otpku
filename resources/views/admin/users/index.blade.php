@extends('layouts.app')
@php($title = 'Pengguna')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><h1 class="text-3xl font-black tracking-tight">Pengguna & saldo</h1><p class="mt-2 text-sm text-slate-500">Cari akun, tinjau status, dan kelola permintaan penghapusan.</p></div><a href="{{ route('admin.users.index', ['deletion_pending' => 1]) }}" class="btn-secondary"><x-icon name="warning" size="size-4" /> Permintaan penghapusan</a></div>
<form class="card mt-6 grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto]" method="get"><input class="input" name="q" value="{{ request('q') }}" placeholder="Username, email, atau WhatsApp"><label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-4 text-sm font-semibold dark:border-white/10"><input type="checkbox" name="deletion_pending" value="1" @checked(request()->boolean('deletion_pending'))> Menunggu hapus akun</label><button class="btn-primary">Cari</button></form>
<div class="mt-6 table-wrap"><table class="table"><thead><tr><th>User</th><th>WhatsApp</th><th>Status</th><th>Saldo</th><th>Penghapusan</th><th>Terdaftar</th><th></th></tr></thead><tbody>
@forelse($users as $user)
<tr><td><div class="font-semibold">{{ $user->username }}</div><div class="text-xs text-slate-500">{{ $user->email }}</div></td><td>{{ $user->whatsapp }}</td><td><x-status :value="$user->status" /></td><td class="font-bold">Rp {{ number_format((float) $user->balance, 0, ',', '.') }}</td><td>@if($user->deletion_request_status)<span class="badge {{ $user->deletion_request_status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300' }}">{{ $user->deletion_request_status }}</span>@else<span class="text-slate-400">—</span>@endif</td><td>{{ $user->created_at->format('d M Y') }}</td><td><a class="btn-secondary !px-3 !py-2" href="{{ route('admin.users.show', $user) }}">Kelola</a></td></tr>
@empty<tr><td colspan="7" class="py-10 text-center text-slate-500">Pengguna tidak ditemukan.</td></tr>@endforelse
</tbody></table></div><div class="mt-6">{{ $users->links() }}</div>
@endsection
