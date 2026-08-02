@extends('layouts.app')
@php($title = 'Dashboard')
@section('content')
<section class="lg:hidden">
    <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#1e293b] via-[#263859] to-[#111827] p-5 text-white shadow-xl">
        <div class="absolute -right-12 -top-12 size-44 rounded-full bg-cyan-400/15 blur-2xl"></div>
        <div class="relative z-10 flex items-start justify-between gap-4">
            <div>
                <span class="badge bg-white/10 text-cyan-200">Kode OTP Cepat dan Aman</span>
                <h1 class="mt-4 text-2xl font-black leading-tight">Aktivasi cepat tanpa SIM fisik</h1>
                <p class="mt-2 max-w-xs text-sm leading-6 text-slate-300">Cari layanan, pilih negara, lalu terima OTP langsung dari dashboard.</p>
            </div>
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-white/10"><x-icon name="shield" size="size-8" /></span>
        </div>
        <div class="relative z-10 mt-5 flex gap-3"><a href="{{ route('services.index') }}" class="btn-primary flex-1">Pilih layanan</a><a href="{{ route('topups.index') }}" class="btn-secondary !border-white/20 !bg-white/10 !text-white">Isi Saldo</a></div>
    </div>

    <a href="{{ route('services.index') }}" class="mt-5 flex items-center gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#0f1729]">
        <x-icon name="search" class="text-cyan-500" />
        <span class="flex-1 text-sm font-semibold text-slate-400">Cari layanan</span>
        <span class="grid size-10 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="filter" /></span>
    </a>
</section>

<div class="hidden flex-col justify-between gap-4 sm:flex-row sm:items-end lg:flex">
    <div><p class="text-sm font-semibold text-violet-600 dark:text-violet-300">Selamat datang kembali,</p><h1 class="section-title mt-1">{{ $user->name }}</h1><p class="section-copy">Kelola saldo, pilih layanan OTP, dan pantau transaksi dari satu dashboard.</p></div>
    <a class="btn-primary" href="{{ route('services.index') }}">Beli layanan OTP <x-icon name="arrow-right" size="size-4" /></a>
</div>

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
    <div class="stat-card col-span-2 sm:col-span-1"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Saldo Rupiah</div><div class="mt-2 text-2xl font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $user->balance, 0, ',', '.') }}</div><a href="{{ route('topups.index') }}" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-violet-600">Tambah saldo <x-icon name="arrow-right" size="size-3" /></a></div>
    <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Order aktif</div><div class="mt-2 text-2xl font-black">{{ $activeOrders->count() }}</div><div class="mt-3 text-xs text-slate-500">Sedang diproses</div></div>
    <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Top up pending</div><div class="mt-2 text-2xl font-black">{{ $pendingTopups->count() }}</div><div class="mt-3 text-xs text-slate-500">Menunggu pembayaran</div></div>
    <div class="stat-card"><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Status akun</div><div class="mt-3"><x-status :value="$user->status" /></div><div class="mt-3 text-xs text-slate-500">Email terverifikasi</div></div>
</div>

<section class="mt-8">
    <div class="flex items-center justify-between gap-4"><div><h2 class="text-2xl font-black">Rekomendasi</h2><p class="mt-1 text-sm text-slate-500">Layanan dengan stok terbesar saat ini.</p></div><a href="{{ route('services.index') }}" class="text-sm font-bold text-violet-600 dark:text-violet-300">Semua</a></div>
    <div class="scrollbar-thin mt-5 flex gap-2 overflow-x-auto pb-2 lg:hidden">
        <a href="{{ route('services.index', ['sort' => 'popular']) }}" class="filter-chip filter-chip-active">Populer</a><a href="{{ route('services.index', ['sort' => 'price_asc']) }}" class="filter-chip">Termurah</a><a href="{{ route('services.index', ['sort' => 'stock']) }}" class="filter-chip">Stok besar</a><a href="{{ route('services.index', ['sort' => 'name']) }}" class="filter-chip">A–Z</a>
    </div>
    <div class="mt-5 grid gap-3 lg:grid-cols-2 2xl:grid-cols-4">
        @forelse($featuredServices as $service)
            <a href="{{ route('services.show', $service) }}" class="service-row">
                <x-service-icon :service="$service" />
                <div class="min-w-0 flex-1"><div class="truncate font-black">{{ trim($service->name) }}</div><div class="mt-1 text-xs text-slate-500">Terendah: <span class="font-bold text-cyan-600 dark:text-cyan-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</span></div><div class="mt-1 text-xs text-slate-400">Tertinggi: Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }} · Stok {{ number_format((int) $service->total_stock) }}</div></div><x-icon name="chevron-right" class="text-slate-400" />
            </a>
        @empty<div class="card col-span-full p-10 text-center text-sm text-slate-500">Katalog belum tersedia.</div>@endforelse
    </div>
</section>

<div class="mt-8 grid gap-6 xl:grid-cols-3">
    <section class="card p-5 xl:col-span-2"><div class="flex items-center justify-between"><h2 class="text-lg font-black">Pesanan aktif</h2><a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('orders.index') }}">Semua</a></div><div class="mt-4 space-y-3">@forelse($activeOrders as $order)<a href="{{ route('orders.show', $order) }}" class="flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 dark:border-white/10 dark:hover:border-violet-400/30 sm:flex-row sm:items-center"><div><div class="font-bold">{{ $order->service_name }} · {{ $order->country_name }}</div><div class="mt-1 text-xs text-slate-500">{{ $order->phone_number ?: 'Nomor sedang disiapkan' }}</div></div><x-status :value="$order->status" /></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-white/10">Belum ada pesanan aktif.</div>@endforelse</div></section>
    <section class="card p-5"><div class="flex items-center justify-between"><h2 class="text-lg font-black">Pengumuman</h2><a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('announcements.index') }}">Semua</a></div><div class="mt-4 space-y-4">@forelse($announcements as $item)<div class="card-soft p-4"><div class="flex items-center gap-2"><h3 class="font-bold">{{ $item->title }}</h3>@if($item->is_pinned)<span title="Disematkan">★</span>@endif</div><p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $item->body }}</p></div>@empty<p class="text-sm text-slate-500">Tidak ada pengumuman.</p>@endforelse</div></section>
</div>

<section class="card mt-6 p-5"><h2 class="text-lg font-black">Mutasi terbaru</h2><div class="mt-4 table-wrap"><table class="table"><thead><tr><th>Waktu</th><th>Keterangan</th><th>Arah</th><th>Nominal</th><th>Saldo</th></tr></thead><tbody>@forelse($recentTransactions as $tx)<tr><td>{{ $tx->created_at->format('d M Y H:i') }}</td><td>{{ $tx->description }}</td><td><x-status :value="$tx->direction" /></td><td class="font-bold {{ $tx->direction === 'credit' ? 'text-emerald-500' : 'text-rose-500' }}">{{ $tx->direction === 'credit' ? '+' : '-' }} Rp {{ number_format((float) $tx->amount, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $tx->balance_after, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-slate-500">Belum ada mutasi.</td></tr>@endforelse</tbody></table></div></section>
@endsection
