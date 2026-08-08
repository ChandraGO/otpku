@extends('layouts.app')
@php($title = 'Riwayat Aktivitas')
@section('content')
<div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
    <h1 class="text-3xl font-black">Riwayat aktivitas</h1>
    <div class="text-sm text-slate-500">Isi saldo, pembelian layanan, pembatalan, refund, dan pergantian penyedia pembayaran.</div>
</div>

<form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" method="get" data-auto-filter>
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Pengguna, transaksi, aktivitas">
    <select class="input" name="type">
        <option value="">Semua aktivitas</option>
        <option value="topup" @selected(request('type')==='topup')>Isi saldo</option>
        <option value="order" @selected(request('type')==='order')>Pesanan layanan</option>
        <option value="payment_gateway" @selected(request('type')==='payment_gateway')>Penyedia pembayaran</option>
    </select>
    <select class="input" name="gateway">
        <option value="">Semua penyedia pembayaran</option>
        <option value="pakasir" @selected(request('gateway')==='pakasir')>Pakasir</option>
        <option value="duitku" @selected(request('gateway')==='duitku')>Duitku</option>
    </select>
    <select class="input" name="status">
        <option value="">Semua status</option>
        @foreach(['creating'=>'Membuat','processing'=>'Diproses','provider_pending'=>'Menunggu penyedia','pending'=>'Menunggu','otp_received'=>'OTP diterima','completed'=>'Selesai','cancelled'=>'Dibatalkan','refunded'=>'Dikembalikan','expired'=>'Kedaluwarsa','failed'=>'Gagal','scheduled'=>'Dijadwalkan','active'=>'Aktif'] as $value=>$label)
            <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
        @endforeach
    </select>
</form>

<div class="mt-6 table-wrap">
    <table class="table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Penyedia</th><th>Nominal</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($activities as $activity)
            <tr>
                <td class="whitespace-nowrap">{{ $activity->created_at->format('d M Y H:i:s') }}</td>
                <td>
                    <div class="font-semibold">{{ $activity->user?->email ?? $activity->actor?->email ?? 'Sistem' }}</div>
                    @if($activity->actor && $activity->actor_id !== $activity->user_id)<div class="text-xs text-slate-500">Admin: {{ $activity->actor->email }}</div>@endif
                </td>
                <td class="min-w-80"><div class="font-semibold">{{ $activity->description }}</div><div class="mt-1 font-mono text-[11px] text-slate-400">{{ $activity->event }}</div></td>
                <td>{{ $activity->gateway ? ucfirst($activity->gateway) : '—' }}</td>
                <td>{{ $activity->amount !== null ? 'Rp '.number_format((float)$activity->amount,0,',','.') : '—' }}</td>
                <td>@if($activity->status)<x-status :value="$activity->status" />@else — @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada aktivitas yang tercatat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $activities->links() }}</div>
@endsection
