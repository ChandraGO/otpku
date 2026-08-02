@extends('layouts.app')
@php($title = 'Admin Dashboard')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Administrator</span><h1 class="section-title mt-4">Ringkasan operasional</h1><p class="section-copy">Pantau penjualan, saldo pengguna, dan kecukupan saldo provider sebelum order masuk.</p></div>
    <a class="btn-secondary" href="{{ route('admin.settings.index', ['tab' => 'sms_virtual']) }}"><x-icon name="settings" size="size-4" /> Pengaturan saldo provider</a>
</div>

@php
    $riskClass = match($riskStatus) {
        'healthy' => 'risk-healthy',
        'warning' => 'risk-warning',
        'critical' => 'risk-critical',
        default => 'risk-unknown',
    };
    $riskTitle = match($riskStatus) {
        'healthy' => 'Saldo provider mencukupi',
        'warning' => 'Cadangan provider di bawah target',
        'critical' => 'Saldo provider menipis — segera top up',
        default => 'Saldo provider belum dapat diverifikasi',
    };
@endphp

<div class="mt-6 rounded-3xl border p-5 {{ $riskClass }}">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-white/60 dark:bg-black/10"><x-icon :name="$riskStatus === 'healthy' ? 'check' : 'warning'" size="size-6" /></span>
            <div><h2 class="font-black">{{ $riskTitle }}</h2><p class="mt-1 text-sm leading-6 opacity-80">Target cadangan = total saldo user + buffer {{ number_format($bufferPercent, 0) }}%. Peringatan minimum disetel Rp {{ number_format($lowBalanceThreshold, 0, ',', '.') }}.</p></div>
        </div>
        @if($reserveGap !== null && $reserveGap > 0)
            <div class="rounded-2xl bg-white/65 px-4 py-3 text-right dark:bg-black/10"><div class="text-[10px] font-black uppercase tracking-wider opacity-70">Kekurangan cadangan</div><div class="mt-1 text-xl font-black">Rp {{ number_format($reserveGap, 0, ',', '.') }}</div></div>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    @foreach([
        ['Pengguna', number_format($stats['users']), 'users'],
        ['Saldo seluruh user', 'Rp '.number_format($stats['user_balance'], 0, ',', '.'), 'wallet'],
        ['Top up berhasil', 'Rp '.number_format($stats['completed_topups'], 0, ',', '.'), 'topup'],
        ['Order hari ini', number_format($stats['orders_today']), 'orders'],
        ['Penjualan hari ini', 'Rp '.number_format($stats['revenue_today'], 0, ',', '.'), 'chart'],
        ['Profit hari ini', 'Rp '.number_format($stats['profit_today'], 0, ',', '.'), 'chart'],
    ] as $stat)
        <div class="stat-card"><span class="grid size-10 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon :name="$stat[2]" /></span><div class="mt-4 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $stat[0] }}</div><div class="mt-2 text-xl font-black">{{ $stat[1] }}</div></div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
    <section class="card p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div><h2 class="text-lg font-black">Coverage saldo provider</h2><p class="mt-1 text-sm text-slate-500">Perbandingan saldo SMS Virtual terhadap kewajiban saldo user.</p></div>
            @if($providerError)<span class="badge bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300">Koneksi gagal</span>@else<span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Live provider</span>@endif
        </div>
        @if($providerError)
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">{{ $providerError }}</div>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="card-soft p-4"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo provider</div><div class="mt-2 text-2xl font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $providerBalanceIdr, 0, ',', '.') }}</div><div class="mt-1 text-xs text-slate-500">Raw {{ is_scalar($providerBalanceRaw) ? $providerBalanceRaw : json_encode($providerBalanceRaw) }} × {{ $providerUnitToIdr }}</div></div>
                <div class="card-soft p-4"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo user</div><div class="mt-2 text-2xl font-black">Rp {{ number_format($stats['user_balance'], 0, ',', '.') }}</div><div class="mt-1 text-xs text-slate-500">Total liability internal</div></div>
                <div class="card-soft p-4"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Target cadangan</div><div class="mt-2 text-2xl font-black">Rp {{ number_format($reserveTarget, 0, ',', '.') }}</div><div class="mt-1 text-xs text-slate-500">Termasuk buffer {{ number_format($bufferPercent, 0) }}%</div></div>
            </div>
            <div class="mt-6"><div class="flex justify-between text-sm font-bold"><span>Coverage ratio</span><span>{{ number_format((float) $coveragePercent, 1) }}%</span></div><div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/5"><div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-cyan-400" style="width: {{ min(100, max(0, (float) $coveragePercent)) }}%"></div></div><p class="mt-2 text-xs text-slate-500">Ideal minimal 100%; target aman mengikuti buffer yang Anda atur.</p></div>
        @endif
    </section>

    <section class="card p-6"><h2 class="text-lg font-black">Tindakan cepat</h2><div class="mt-5 grid gap-3"><a href="{{ route('admin.settings.index', ['tab' => 'sms_virtual']) }}" class="btn-primary justify-between">Atur batas saldo <x-icon name="arrow-right" size="size-4" /></a><a href="{{ route('admin.users.index') }}" class="btn-secondary justify-between">Kelola saldo user <x-icon name="arrow-right" size="size-4" /></a><a href="{{ route('admin.topups.index') }}" class="btn-secondary justify-between">Verifikasi top up <x-icon name="arrow-right" size="size-4" /></a><a href="{{ route('admin.reports.index') }}" class="btn-secondary justify-between">Lihat laporan <x-icon name="arrow-right" size="size-4" /></a></div></section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-5"><div class="flex justify-between"><h2 class="font-black">Pesanan terbaru</h2><a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.orders.index') }}">Semua</a></div><div class="mt-4 space-y-3">@forelse($recentOrders as $order)<a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 dark:border-white/10"><div class="min-w-0"><div class="truncate font-bold">{{ $order->service_name }} · {{ $order->user?->email }}</div><div class="mt-1 text-xs text-slate-500">Rp {{ number_format((float) $order->sell_price, 0, ',', '.') }}</div></div><x-status :value="$order->status" /></a>@empty<p class="text-sm text-slate-500">Belum ada pesanan.</p>@endforelse</div></section>
    <section class="card p-5"><div class="flex justify-between"><h2 class="font-black">Top up terbaru</h2><a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.topups.index') }}">Semua</a></div><div class="mt-4 space-y-3">@forelse($recentTopups as $topup)<a href="{{ route('admin.topups.show', $topup) }}" class="flex justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 dark:border-white/10"><div class="min-w-0"><div class="truncate font-bold">{{ $topup->user?->email }}</div><div class="mt-1 text-xs text-slate-500">{{ $topup->order_id }} · Rp {{ number_format((float) $topup->amount, 0, ',', '.') }}</div></div><x-status :value="$topup->status" /></a>@empty<p class="text-sm text-slate-500">Belum ada top up.</p>@endforelse</div></section>
</div>
@endsection
