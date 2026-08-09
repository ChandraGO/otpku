@extends('layouts.app')
@php
    $title = 'Dasbor';
@endphp
@section('content')
@if($loginAnnouncement)
<div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="card max-h-[90vh] w-full max-w-2xl overflow-y-auto" @click.outside="open = false">
        @if($loginAnnouncement->imageUrl())
            <div class="px-4 pt-4 sm:px-5 sm:pt-5">
                <img src="{{ $loginAnnouncement->imageUrl() }}" alt="Gambar {{ $loginAnnouncement->title }}" class="mx-auto block h-auto max-h-[68vh] w-auto max-w-full rounded-[1.35rem] border border-slate-200 object-contain shadow-sm dark:border-white/10">
            </div>
        @endif
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <x-announcement-category :value="$loginAnnouncement->type" />
                    <h2 class="mt-3 text-2xl font-black">{{ $loginAnnouncement->title }}</h2>
                </div>
                <button type="button" class="btn-secondary !p-2" @click="open = false" aria-label="Tutup pengumuman">×</button>
            </div>
            <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $loginAnnouncement->body }}</p>
            <button type="button" class="btn-primary mt-6 w-full" @click="open = false">Mengerti</button>
        </div>
    </div>
</div>
@endif

<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-violet-600 via-violet-600 to-indigo-700 p-6 text-white shadow-xl shadow-violet-500/10 dark:border-white/10 sm:p-7">
    <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
        <div>
            <div class="text-xs font-black uppercase tracking-[.2em] text-violet-100/75">Dashboard akun</div>
            <h1 class="mt-2 text-2xl font-black sm:text-3xl">Selamat datang, {{ $user->name }} 👋</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-violet-100/80">Pantau saldo, transaksi OTP, layanan favorit, dan informasi terbaru dari satu halaman.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('services.index') }}" class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-black text-violet-700 shadow-lg transition hover:-translate-y-0.5">Pemesanan <x-icon name="arrow-right" size="size-4" /></a>
            @if($user->isAdmin())
                <a href="https://sms-virtual.net" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-2xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-black text-white backdrop-blur"><x-icon name="topup" size="size-4" /> Isi Saldo Penyedia</a>
            @else
                <a href="{{ route('topups.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-black text-white backdrop-blur"><x-icon name="topup" size="size-4" /> Isi Saldo</a>
            @endif
        </div>
    </div>
</section>

<section class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
    <article class="card p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"><x-icon name="wallet" size="size-5" /></span>
            <div class="min-w-0"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $dashboardBalanceLabel }}</div><div class="mt-1 truncate text-xl font-black">{{ $dashboardBalanceAvailable ? 'Rp '.number_format((float) $dashboardBalance, 0, ',', '.') : '—' }}</div></div>
        </div>
    </article>
    <article class="card p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-300"><x-icon name="user" size="size-5" /></span>
            <div><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Level akun</div><div class="mt-1 text-xl font-black">{{ $user->isAdmin() ? 'Admin' : 'Member' }}</div></div>
        </div>
    </article>
    <article class="card p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300"><x-icon name="orders" size="size-5" /></span>
            <div><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pesanan Anda</div><div class="mt-1 text-xl font-black">{{ number_format((int) $totalOrders) }} Trx</div></div>
        </div>
    </article>
    <article class="card p-4 sm:p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-300"><x-icon name="chart" size="size-5" /></span>
            <div class="min-w-0"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pengeluaran</div><div class="mt-1 truncate text-xl font-black">Rp {{ number_format((float) $totalSpent, 0, ',', '.') }}</div></div>
        </div>
    </article>
</section>

<section class="mt-4 grid gap-3 sm:grid-cols-2">
    <a href="{{ route('services.index') }}" class="flex items-center justify-center gap-2 rounded-2xl bg-violet-600 px-5 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-violet-500"><x-icon name="services" size="size-5" /> Pemesanan</a>
    @if($user->isAdmin())
        <a href="https://sms-virtual.net" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-emerald-500"><x-icon name="topup" size="size-5" /> Deposit Penyedia</a>
    @else
        <a href="{{ route('topups.index') }}" class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-emerald-500"><x-icon name="topup" size="size-5" /> Isi Saldo</a>
    @endif
</section>

<section class="mt-5 space-y-3">
    <details class="card group overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-black">
            <span class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-rose-500/10 text-rose-500"><x-icon name="chart" size="size-4" /></span> Top 10 Layanan Terlaris Anda</span>
            <x-icon name="chevron-right" class="transition group-open:rotate-90" size="size-4" />
        </summary>
        <div class="border-t border-slate-200 p-4 dark:border-white/10">
            <div class="grid gap-2 sm:grid-cols-2">
                @forelse($topServices as $service)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-white/[.03]"><span class="truncate font-bold">{{ $service->service_name }}</span><span class="shrink-0 text-xs font-black text-violet-600 dark:text-violet-300">{{ number_format((int) $service->total) }}×</span></div>
                @empty
                    <div class="col-span-full py-5 text-center text-sm text-slate-500">Belum ada riwayat layanan.</div>
                @endforelse
            </div>
        </div>
    </details>

    <details class="card group overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-black">
            <span class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-amber-500/10 text-amber-500"><x-icon name="bolt" size="size-4" /></span> Sering Anda Pesan Bulan Ini</span>
            <x-icon name="chevron-right" class="transition group-open:rotate-90" size="size-4" />
        </summary>
        <div class="border-t border-slate-200 p-4 dark:border-white/10">
            <div class="flex flex-wrap gap-2">
                @forelse($monthFrequentServices as $service)
                    <span class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold dark:border-white/10">{{ $service->service_name }} <span class="ml-1 text-violet-600 dark:text-violet-300">{{ (int) $service->total }}×</span></span>
                @empty
                    <div class="w-full py-5 text-center text-sm text-slate-500">Belum ada pesanan bulan ini.</div>
                @endforelse
            </div>
        </div>
    </details>

    <details class="card group overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-black">
            <span class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-sky-500/10 text-sky-500"><x-icon name="history" size="size-4" /></span> Terakhir Anda Pesan Bulan Ini</span>
            <x-icon name="chevron-right" class="transition group-open:rotate-90" size="size-4" />
        </summary>
        <div class="border-t border-slate-200 dark:border-white/10">
            @forelse($monthRecentOrders as $order)
                <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 text-sm last:border-b-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/[.03]"><span class="min-w-0"><strong class="block truncate">{{ $order->service_name }}</strong><span class="mt-1 block text-xs text-slate-400">{{ $order->created_at->format('d M Y H:i') }} · {{ $order->country_name }}</span></span><x-status :value="$order->status" /></a>
            @empty
                <div class="p-6 text-center text-sm text-slate-500">Belum ada pesanan bulan ini.</div>
            @endforelse
        </div>
    </details>
</section>

<section class="card mt-6 p-5 sm:p-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-pink-500/10 text-pink-500"><x-icon name="announcement" size="size-5" /></span>
            <div>
                <div class="text-xs font-black uppercase tracking-[.15em] text-slate-400">Berita & Informasi</div>
                <h2 class="mt-1 text-lg font-black">Informasi terbaru</h2>
            </div>
        </div>
        <a href="{{ route('announcements.index') }}" class="text-xs font-black text-violet-600 hover:text-violet-500 dark:text-violet-300">Lihat semua</a>
    </div>

    <div class="mt-6 space-y-3">
        @forelse($latestAnnouncements as $item)
            @php
                $accentClass = match(strtolower((string) $item->type)) {
                    'important', 'warning', 'danger' => 'border-rose-500',
                    'news' => 'border-slate-500',
                    'update' => 'border-violet-500',
                    'deposit' => 'border-emerald-500',
                    'service' => 'border-amber-500',
                    default => 'border-sky-500',
                };
            @endphp
            <article class="rounded-2xl border border-slate-200 border-l-4 {{ $accentClass }} bg-slate-50/70 p-4 dark:border-y-white/10 dark:border-r-white/10 dark:bg-white/[.025]">
                <div class="flex flex-wrap items-center gap-2">
                    <x-announcement-category :value="$item->type" />
                    @if($item->is_pinned)
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-400/35 bg-amber-400/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-600 dark:text-amber-300" title="Pengumuman disematkan">📌 Disematkan</span>
                    @endif
                    <span class="text-[10px] font-semibold text-slate-400">{{ $item->created_at->format('d M Y · H:i') }}</span>
                </div>
                <h3 class="mt-2 font-black">{{ $item->title }}</h3>
                @if($item->imageUrl())
                    <img src="{{ $item->imageUrl() }}" alt="Gambar {{ $item->title }}" class="mt-3 block h-auto max-h-[34rem] w-auto max-w-full rounded-[1.15rem] border border-slate-200 object-contain shadow-sm dark:border-white/10" loading="lazy" decoding="async">
                @endif
                <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm leading-6 text-slate-500">{{ $item->body }}</p>
            </article>
        @empty
            <div class="py-6 text-sm text-slate-500">Belum ada informasi terbaru.</div>
        @endforelse
    </div>
</section>
@endsection
