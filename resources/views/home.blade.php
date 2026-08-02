@extends('layouts.guest')
@php($title = $siteName)
@section('content')
<section class="page-grid relative overflow-hidden">
    <div class="mx-auto grid min-h-[720px] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:py-24">
        <div class="relative z-10">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <span class="size-2 rounded-full bg-emerald-400"></span>
                Fast & Secure OTP Codes
            </span>
            <h1 class="text-balance mt-7 max-w-3xl text-4xl font-black leading-[1.08] tracking-tight text-slate-950 dark:text-white sm:text-6xl lg:text-7xl">
                Aktivasi SMS OTP virtual, cepat dan mudah dipantau.
            </h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-400">
                {{ $siteDescription ?: 'Dapatkan nomor virtual untuk verifikasi akun tanpa SIM fisik. Harga transparan, stok tersinkron, dan status OTP tampil real-time pada satu dashboard.' }}
            </p>
            <div class="mt-9 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('services.index') }}" class="btn-primary">Pilih layanan <x-icon name="arrow-right" size="size-4" /></a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary">Mulai sekarang <x-icon name="arrow-right" size="size-4" /></a>
                    <a href="{{ route('login') }}" class="btn-secondary">Masuk</a>
                @endauth
                <a href="{{ route('pricing') }}" class="btn-secondary">Lihat harga</a>
            </div>
            <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10"><x-icon name="check" size="size-4" /></span> Akses aman dan praktis</span>
                <span class="inline-flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10"><x-icon name="check" size="size-4" /></span> Saldo dalam Rupiah</span>
                <span class="inline-flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10"><x-icon name="check" size="size-4" /></span> Status pesanan real-time</span>
            </div>
        </div>

        <div class="relative">
            <div class="absolute -inset-8 rounded-full bg-gradient-to-br from-violet-500/20 to-cyan-400/20 blur-3xl"></div>
            <div class="card relative overflow-hidden p-4 sm:p-6">
                <div class="rounded-[1.4rem] bg-gradient-to-br from-[#1d2742] via-[#253554] to-[#19243b] p-5 text-white sm:p-7">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">Secure verification</div>
                            <h2 class="mt-2 text-2xl font-black">OTP delivered instantly</h2>
                        </div>
                        <span class="grid size-12 place-items-center rounded-2xl bg-white/10"><x-icon name="shield" size="size-7" /></span>
                    </div>
                    <img src="/illustrations/otp-hero.svg" alt="Ilustrasi verifikasi OTP" class="mt-4 w-full" loading="eager">
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="card-soft p-3 text-center"><div class="text-lg font-black text-violet-600 dark:text-violet-300">{{ number_format($serviceCount) }}+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Services</div></div>
                    <div class="card-soft p-3 text-center"><div class="text-lg font-black text-violet-600 dark:text-violet-300">{{ number_format($countryCount) }}+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Countries</div></div>
                    <div class="card-soft p-3 text-center"><div class="text-lg font-black text-violet-600 dark:text-violet-300">24/7</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Monitoring</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
    <div class="mx-auto max-w-3xl text-center">
        <span class="badge bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Summary of key metrics and insights</span>
        <h2 class="section-title mt-5">Dirancang untuk transaksi OTP yang sederhana dan transparan</h2>
        <p class="section-copy mx-auto">Pilih layanan, selesaikan pembayaran dari saldo, lalu pantau kode OTP pada satu dashboard.</p>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="wallet" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Harga Transparan</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Harga ditampilkan jelas dalam Rupiah sebelum pesanan dibuat.</p>
        </article>
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="bolt" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Instant Delivery</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Nomor, status, isi SMS, kode OTP, dan masa berlaku ditampilkan langsung pada halaman pesanan.</p>
        </article>
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-icon name="shield" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Secure by Default</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Akun dan transaksi dilindungi dengan proses keamanan yang terintegrasi.</p>
        </article>
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-20 dark:border-white/10 dark:bg-[#0a1020]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Popular services</span>
                <h2 class="section-title mt-4">Layanan dengan stok tersedia</h2>
                <p class="section-copy">Logo, harga terendah, harga tertinggi, dan total stok ditampilkan dari katalog terbaru.</p>
            </div>
            <a href="{{ auth()->check() ? route('services.index') : route('pricing') }}" class="btn-secondary">Lihat semua <x-icon name="arrow-right" size="size-4" /></a>
        </div>
        <div class="mt-9 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($featuredServices as $service)
                <a href="{{ auth()->check() ? route('services.show', $service) : route('pricing', ['q' => $service->name]) }}" class="service-row">
                    <x-service-icon :service="$service" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-black">{{ trim($service->name) }}</div>
                        <div class="mt-1 text-xs text-slate-500">Mulai Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</div>
                        <div class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-300">Stok {{ number_format((int) $service->total_stock) }}</div>
                    </div>
                    <x-icon name="chevron-right" class="text-slate-400" />
                </a>
            @empty
                <div class="card col-span-full p-10 text-center text-sm text-slate-500">Katalog layanan belum tersedia.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
    <div class="grid gap-8 lg:grid-cols-3">
        @foreach([
            ['1', 'Pilih layanan', 'Cari aplikasi, pilih negara, bandingkan harga, lalu lihat stok yang tersedia.'],
            ['2', 'Bayar dari saldo', 'Saldo Rupiah digunakan untuk menyelesaikan pesanan.'],
            ['3', 'Terima OTP', 'Pantau nomor, pesan, kode OTP, status, dan waktu kedaluwarsa dari halaman order.'],
        ] as $step)
            <article class="card p-7">
                <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 text-lg font-black text-white">{{ $step[0] }}</span>
                <h3 class="mt-5 text-xl font-black">{{ $step[1] }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $step[2] }}</p>
            </article>
        @endforeach
    </div>

    @if($announcements->isNotEmpty())
        <div class="card mt-10 p-6">
            <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300"><x-icon name="announcement" /></span><h2 class="text-lg font-black">Pengumuman</h2></div>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach($announcements as $announcement)
                    <article class="card-soft p-4"><h3 class="font-bold">{{ $announcement->title }}</h3><p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ $announcement->body }}</p></article>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
