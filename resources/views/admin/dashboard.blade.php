@extends('layouts.app')

<?php
    $title = 'Admin Dashboard';

    $safeStats = array_merge([
        'users' => 0,
        'user_balance' => 0,
        'completed_topups' => 0,
        'orders_today' => 0,
        'revenue_today' => 0,
        'profit_today' => 0,
    ], is_array($stats ?? null) ? $stats : []);

    $status = in_array(($riskStatus ?? 'unknown'), ['healthy', 'warning', 'critical'], true)
        ? $riskStatus
        : 'unknown';

    $riskClasses = [
        'healthy' => 'risk-healthy',
        'warning' => 'risk-warning',
        'critical' => 'risk-critical',
        'unknown' => 'risk-unknown',
    ];

    $riskTitles = [
        'healthy' => 'Saldo layanan mencukupi',
        'warning' => 'Cadangan saldo di bawah target',
        'critical' => 'Saldo layanan menipis — segera top up',
        'unknown' => 'Saldo layanan belum diperbarui',
    ];

    $riskClass = $riskClasses[$status];
    $riskTitle = $riskTitles[$status];
    $riskIcon = $status === 'healthy' ? 'check' : 'warning';
    $balanceIdr = is_numeric($providerBalanceIdr ?? null)
        ? (float) $providerBalanceIdr
        : null;
    $balanceRaw = is_numeric($providerBalanceRaw ?? null)
        ? (float) $providerBalanceRaw
        : null;
    $unitToIdr = is_numeric($providerUnitToIdr ?? null)
        ? (float) $providerUnitToIdr
        : 1.0;
    $reserveTargetValue = is_numeric($reserveTarget ?? null)
        ? (float) $reserveTarget
        : 0.0;
    $reserveGapValue = is_numeric($reserveGap ?? null)
        ? (float) $reserveGap
        : null;
    $coverageValue = is_numeric($coveragePercent ?? null)
        ? (float) $coveragePercent
        : null;
    $bufferValue = is_numeric($bufferPercent ?? null)
        ? (float) $bufferPercent
        : 0.0;
    $thresholdValue = is_numeric($lowBalanceThreshold ?? null)
        ? (float) $lowBalanceThreshold
        : 0.0;

    $statCards = [
        ['Pengguna', number_format((int) $safeStats['users']), 'users'],
        ['Saldo seluruh user', 'Rp '.number_format((float) $safeStats['user_balance'], 0, ',', '.'), 'wallet'],
        ['Top up berhasil', 'Rp '.number_format((float) $safeStats['completed_topups'], 0, ',', '.'), 'topup'],
        ['Order hari ini', number_format((int) $safeStats['orders_today']), 'orders'],
        ['Penjualan hari ini', 'Rp '.number_format((float) $safeStats['revenue_today'], 0, ',', '.'), 'chart'],
        ['Profit hari ini', 'Rp '.number_format((float) $safeStats['profit_today'], 0, ',', '.'), 'chart'],
    ];
?>

@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
            Administrator
        </span>
        <h1 class="section-title mt-4">Ringkasan operasional</h1>
        <p class="section-copy">Pantau transaksi, saldo pengguna, dan kecukupan saldo layanan.</p>
    </div>

    <a class="btn-secondary" href="{{ route('admin.settings.index', ['tab' => 'sms_virtual']) }}">
        <x-icon name="settings" size="size-4" />
        Pengaturan saldo layanan
    </a>
</div>

<div class="mt-6 rounded-3xl border p-5 {{ $riskClass }}">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-white/60 dark:bg-black/10">
                <x-icon :name="$riskIcon" size="size-6" />
            </span>
            <div>
                <h2 class="font-black">{{ $riskTitle }}</h2>
                <p class="mt-1 text-sm leading-6 opacity-80">
                    Target cadangan mengikuti total saldo user dan buffer {{ number_format($bufferValue, 0) }}%.
                    Batas minimum Rp {{ number_format($thresholdValue, 0, ',', '.') }}.
                </p>
            </div>
        </div>

        @if($reserveGapValue !== null && $reserveGapValue > 0)
            <div class="rounded-2xl bg-white/65 px-4 py-3 text-right dark:bg-black/10">
                <div class="text-[10px] font-black uppercase tracking-wider opacity-70">Kekurangan cadangan</div>
                <div class="mt-1 text-xl font-black">Rp {{ number_format($reserveGapValue, 0, ',', '.') }}</div>
            </div>
        @endif
    </div>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    @foreach($statCards as $stat)
        <div class="stat-card">
            <span class="grid size-10 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <x-icon :name="$stat[2]" />
            </span>
            <div class="mt-4 text-xs font-bold uppercase tracking-wider text-slate-400">{{ $stat[0] }}</div>
            <div class="mt-2 text-xl font-black">{{ $stat[1] }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
    <section class="card p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <h2 class="text-lg font-black">Coverage saldo layanan</h2>
                <p class="mt-1 text-sm text-slate-500">Perbandingan saldo tersedia terhadap total saldo pengguna.</p>
            </div>

            @if($balanceIdr === null)
                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Belum diperbarui</span>
            @else
                <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Data tersimpan</span>
            @endif
        </div>

        @if($balanceIdr === null)
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                {{ ($providerError ?? null) ?: 'Saldo belum diperbarui. Jalankan tes saldo melalui halaman pengaturan.' }}
            </div>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="card-soft p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo layanan</div>
                    <div class="mt-2 text-2xl font-black text-violet-700 dark:text-violet-300">
                        Rp {{ number_format($balanceIdr, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        Nilai {{ number_format((float) $balanceRaw, 2, ',', '.') }} × {{ number_format($unitToIdr, 2, ',', '.') }}
                    </div>
                </div>

                <div class="card-soft p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo user</div>
                    <div class="mt-2 text-2xl font-black">
                        Rp {{ number_format((float) $safeStats['user_balance'], 0, ',', '.') }}
                    </div>
                </div>

                <div class="card-soft p-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Target cadangan</div>
                    <div class="mt-2 text-2xl font-black">
                        Rp {{ number_format($reserveTargetValue, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-slate-500">Termasuk buffer {{ number_format($bufferValue, 0) }}%</div>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex justify-between text-sm font-bold">
                    <span>Coverage ratio</span>
                    <span>{{ number_format((float) $coverageValue, 1) }}%</span>
                </div>
                <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/5">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-violet-600 to-cyan-400"
                        style="width: {{ min(100, max(0, (float) $coverageValue)) }}%"
                    ></div>
                </div>
            </div>
        @endif
    </section>

    <section class="card p-6">
        <h2 class="text-lg font-black">Tindakan cepat</h2>
        <div class="mt-5 grid gap-3">
            <a href="{{ route('admin.settings.index', ['tab' => 'sms_virtual']) }}" class="btn-primary justify-between">
                Perbarui saldo layanan <x-icon name="arrow-right" size="size-4" />
            </a>
            <a href="{{ route('admin.users.index') }}" class="btn-secondary justify-between">
                Kelola saldo user <x-icon name="arrow-right" size="size-4" />
            </a>
            <a href="{{ route('admin.topups.index') }}" class="btn-secondary justify-between">
                Verifikasi top up <x-icon name="arrow-right" size="size-4" />
            </a>
            <a href="{{ route('admin.reports.index') }}" class="btn-secondary justify-between">
                Lihat laporan <x-icon name="arrow-right" size="size-4" />
            </a>
        </div>
    </section>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-5">
        <div class="flex justify-between">
            <h2 class="font-black">Pesanan terbaru</h2>
            <a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.orders.index') }}">Semua</a>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($recentOrders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 dark:border-white/10">
                    <div class="min-w-0">
                        <div class="truncate font-bold">{{ $order->service_name }} · {{ $order->user?->email }}</div>
                        <div class="mt-1 text-xs text-slate-500">Rp {{ number_format((float) $order->sell_price, 0, ',', '.') }}</div>
                    </div>
                    <x-status :value="$order->status" />
                </a>
            @empty
                <p class="text-sm text-slate-500">Belum ada pesanan.</p>
            @endforelse
        </div>
    </section>

    <section class="card p-5">
        <div class="flex justify-between">
            <h2 class="font-black">Top up terbaru</h2>
            <a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.topups.index') }}">Semua</a>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($recentTopups as $topup)
                <a href="{{ route('admin.topups.show', $topup) }}" class="flex justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 dark:border-white/10">
                    <div class="min-w-0">
                        <div class="truncate font-bold">{{ $topup->user?->email }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ $topup->order_id }} · Rp {{ number_format((float) $topup->amount, 0, ',', '.') }}
                        </div>
                    </div>
                    <x-status :value="$topup->status" />
                </a>
            @empty
                <p class="text-sm text-slate-500">Belum ada top up.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
