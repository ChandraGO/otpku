@extends('layouts.app')

@section('content')
<div data-kodeotp-safe-admin="v16">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold text-violet-600 dark:text-violet-300">
                Administrator
            </p>
            <h1 class="mt-1 text-3xl font-black tracking-tight">
                Ringkasan operasional
            </h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Pantau saldo layanan, saldo pengguna, transaksi, dan pesanan.
            </p>
        </div>

        <a
            class="btn-secondary"
            href="{{ route('admin.settings.index', ['tab' => 'sms_virtual']) }}"
        >
            Pengaturan SMS Virtual
        </a>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Pengguna
            </div>
            <div class="mt-2 text-2xl font-black">
                {{ number_format((int) ($stats['users'] ?? 0)) }}
            </div>
        </section>

        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Total saldo pengguna
            </div>
            <div class="mt-2 text-2xl font-black">
                Rp {{ number_format((float) ($stats['user_balance'] ?? 0), 0, ',', '.') }}
            </div>
        </section>

        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Saldo layanan
            </div>

            @if(is_numeric($providerBalanceIdr ?? null))
                <div class="mt-2 text-2xl font-black text-violet-600 dark:text-violet-300">
                    Rp {{ number_format((float) $providerBalanceIdr, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    @if(($providerBalanceSource ?? null) === 'provider')
                        Live dari provider saat dashboard dibuka.
                    @else
                        {{ $providerError ?: 'Saldo terakhir yang berhasil disinkronkan.' }}
                    @endif
                </div>
            @else
                <div class="mt-2 text-lg font-black text-amber-600 dark:text-amber-300">
                    Belum diperbarui
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ $providerError ?: 'Periksa API key dan koneksi SMS Virtual.' }}
                </div>
            @endif
        </section>

        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Top up berhasil
            </div>
            <div class="mt-2 text-2xl font-black">
                Rp {{ number_format((float) ($stats['completed_topups'] ?? 0), 0, ',', '.') }}
            </div>
        </section>

        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Pesanan hari ini
            </div>
            <div class="mt-2 text-2xl font-black">
                {{ number_format((int) ($stats['orders_today'] ?? 0)) }}
            </div>
        </section>

        <section class="stat-card">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">
                Penjualan hari ini
            </div>
            <div class="mt-2 text-2xl font-black">
                Rp {{ number_format((float) ($stats['revenue_today'] ?? 0), 0, ',', '.') }}
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-black">Pesanan terbaru</h2>
                <a
                    class="text-sm font-bold text-violet-600 dark:text-violet-300"
                    href="{{ route('admin.orders.index') }}"
                >
                    Lihat semua
                </a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse(($recentOrders ?? []) as $order)
                    <a
                        class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4 dark:border-white/10"
                        href="{{ route('admin.orders.show', $order) }}"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-bold">
                                {{ $order->service_name ?? 'Layanan OTP' }}
                            </div>
                            <div class="mt-1 truncate text-xs text-slate-500">
                                {{ $order->user?->email ?? 'Pengguna' }}
                                ·
                                Rp {{ number_format((float) ($order->sell_price ?? 0), 0, ',', '.') }}
                            </div>
                        </div>

                        <span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">
                            {{ strtoupper((string) ($order->status ?? '-')) }}
                        </span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm text-slate-500 dark:border-white/10">
                        Belum ada pesanan.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-black">Top up terbaru</h2>
                <a
                    class="text-sm font-bold text-violet-600 dark:text-violet-300"
                    href="{{ route('admin.topups.index') }}"
                >
                    Lihat semua
                </a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse(($recentTopups ?? []) as $topup)
                    <a
                        class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4 dark:border-white/10"
                        href="{{ route('admin.topups.show', $topup) }}"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-bold">
                                {{ $topup->user?->email ?? 'Pengguna' }}
                            </div>
                            <div class="mt-1 truncate text-xs text-slate-500">
                                {{ $topup->order_id ?? '-' }}
                                ·
                                Rp {{ number_format((float) ($topup->amount ?? 0), 0, ',', '.') }}
                            </div>
                        </div>

                        <span class="badge bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">
                            {{ strtoupper((string) ($topup->status ?? '-')) }}
                        </span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm text-slate-500 dark:border-white/10">
                        Belum ada top up.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
