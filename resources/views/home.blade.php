@extends('layouts.guest')
@php($title = $siteName)
@section('content')
<section class="page-grid relative overflow-hidden">
    <div class="mx-auto grid min-h-[720px] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:py-24">
        <div class="relative z-10">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <span class="size-2 rounded-full bg-emerald-400"></span>
                Kode OTP Cepat & Aman
            </span>
            <h1 class="text-balance mt-7 max-w-3xl text-4xl font-black leading-[1.08] tracking-tight text-slate-950 dark:text-white sm:text-6xl lg:text-7xl">
                Aktivasi SMS OTP virtual, cepat dan mudah dipantau.
            </h1>
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
                <span class="inline-flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10"><x-icon name="check" size="size-4" /></span> Status pesanan waktu nyata</span>
            </div>
        </div>

        <div class="relative">
            <div class="absolute -inset-8 rounded-full bg-gradient-to-br from-violet-500/20 to-cyan-400/20 blur-3xl"></div>
            <div class="card relative overflow-hidden p-4 sm:p-6">
                <div class="rounded-[1.4rem] bg-gradient-to-br from-[#1d2742] via-[#253554] to-[#19243b] p-5 text-white sm:p-7">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">Verifikasi aman</div>
                            <h2 class="mt-2 text-2xl font-black">OTP diterima langsung</h2>
                        </div>
                        <span class="grid size-12 place-items-center rounded-2xl bg-white/10"><x-icon name="shield" size="size-7" /></span>
                    </div>
                    <div class="otp-flow mt-6" aria-label="Animasi alur User ke Web lalu menerima SMS OTP">
                        <div class="otp-flow-line" aria-hidden="true"></div>
                        <span class="otp-flow-pulse otp-flow-pulse-a" aria-hidden="true"></span>
                        <span class="otp-flow-pulse otp-flow-pulse-b" aria-hidden="true"></span>

                        <div class="relative z-10 grid grid-cols-3 items-center gap-3 sm:gap-5">
                            <div class="otp-flow-node otp-flow-user">
                                <span class="otp-flow-icon"><x-icon name="user" size="size-7" /></span>
                                <span class="otp-flow-label">User</span>
                                <span class="otp-flow-state">Minta OTP</span>
                            </div>
                            <div class="otp-flow-node otp-flow-web">
                                <span class="otp-flow-icon"><x-icon name="globe" size="size-7" /></span>
                                <span class="otp-flow-label">Web</span>
                                <span class="otp-flow-state">Verifikasi</span>
                            </div>
                            <div class="otp-flow-node otp-flow-sms">
                                <span class="otp-flow-icon"><x-icon name="mail" size="size-7" /></span>
                                <span class="otp-flow-label">SMS</span>
                                <span class="otp-flow-state">OTP masuk</span>
                            </div>
                        </div>

                        <div class="otp-sms-card">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-300/15 text-cyan-200"><x-icon name="check" size="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] font-black uppercase tracking-[.16em] text-cyan-300">SMS diterima</div>
                                <div class="mt-1 font-mono text-lg font-black tracking-[.22em] text-white">842 193</div>
                            </div>
                            <span class="otp-live-dot" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $serviceCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Layanan</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $countryCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Negara</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-to="24" data-count-suffix="/7" class="text-lg font-black text-violet-600 dark:text-violet-300">0/7</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pemantauan</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
    <div class="mx-auto max-w-3xl text-center">
        <span class="badge bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300">Ringkasan layanan</span>
        <h2 class="section-title mt-5">Dirancang untuk transaksi OTP yang sederhana dan transparan</h2>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="wallet" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Harga Transparan</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Harga ditampilkan jelas dalam Rupiah sebelum pesanan dibuat.</p>
        </article>
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="bolt" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Pengiriman Cepat</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Nomor, status, isi SMS, kode OTP, dan masa berlaku ditampilkan langsung pada halaman pesanan.</p>
        </article>
        <article class="card p-7">
            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-icon name="shield" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Aman Sejak Awal</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Akun dan transaksi dilindungi dengan proses keamanan yang terintegrasi.</p>
        </article>
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-20 dark:border-white/10 dark:bg-[#0a1020]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Layanan populer</span>
                <h2 class="section-title mt-4">Layanan dengan stok tersedia</h2>
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
    <div class="mb-8">
        <h2 class="section-title">Cara kerja pemesanan OTP</h2>
    </div>
    <div class="grid gap-8 lg:grid-cols-3">
        @foreach([
            ['1', 'Pilih layanan', 'Pilih aplikasi yang ingin diverifikasi, tentukan negara atau operator, lalu pilih harga dengan stok yang masih tersedia.'],
            ['2', 'Bayar dari saldo', 'Harga pesanan dipotong langsung dari saldo Rupiah akun setelah Anda menekan tombol Pesan. Jika saldo kurang, isi saldo terlebih dahulu.'],
            ['3', 'Terima OTP', 'Setelah nomor virtual aktif, gunakan nomor tersebut pada layanan tujuan. SMS dan kode OTP yang masuk akan tampil pada halaman detail pesanan beserta status dan waktu kedaluwarsa.'],
        ] as $step)
            <article class="card p-7">
                <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-400 text-lg font-black text-white">{{ $step[0] }}</span>
                <h3 class="mt-5 text-xl font-black">{{ $step[1] }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $step[2] }}</p>
            </article>
        @endforeach
    </div>

</section>

<section id="faq" class="border-t border-slate-200 bg-white py-20 dark:border-white/10 dark:bg-[#0a1020]">
    <div class="mx-auto max-w-4xl px-4 sm:px-6">
        <div class="text-center">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Tanya Jawab</span>
            <h2 class="section-title mt-5">Pertanyaan yang sering diajukan</h2>
        </div>
        <div class="mt-10 space-y-3">
            @foreach([
                ['Bagaimana cara memesan nomor OTP?', 'Pilih layanan, tentukan negara atau operator, pastikan saldo mencukupi, lalu tekan tombol Pesan. Setelah nomor tersedia, gunakan aksi Siap sebelum meminta kode dari aplikasi tujuan. Nomor dan status akan tampil pada halaman detail pesanan.'],
                ['Apakah OTP aman digunakan?', 'Kode OTP hanya ditampilkan pada akun pemesan dan digunakan untuk layanan yang dipilih. Keamanan akun tujuan tetap menjadi tanggung jawab pengguna.'],
                ['Apa yang harus dilakukan jika OTP belum masuk?', 'Gunakan aksi kirim ulang selama waktu pesanan masih aktif. Bila tetap tidak diterima, batalkan pesanan sebelum waktu habis jika status penyedia mengizinkan.'],
                ['Apakah kirim ulang OTP dikenakan biaya?', 'Tidak ada biaya tambahan dari platform untuk aksi Kirim ulang selama pesanan masih aktif. Setelah OTP berhasil digunakan, tekan Selesai untuk mengakhiri layanan. Kebijakan akhir tetap mengikuti respons penyedia.'],
                ['Kapan saldo dikembalikan?', 'Saldo dikembalikan untuk pesanan yang dibatalkan atau gagal sesuai status penyedia dan kebijakan pengembalian saldo platform. Pesanan yang sudah selesai atau sudah menerima OTP tidak dapat dikembalikan saldonya.'],
                ['Apakah layanan dapat dihubungkan ke bot Telegram?', 'Bisa. Setiap pengguna memiliki API key sendiri pada Pengaturan Akun dan dapat memakai endpoint API pelanggan yang tersedia di menu Dokumentasi API.'],
            ] as $index => $faq)
                <details class="faq-item card overflow-hidden" @if($index === 0) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 font-black sm:px-6">
                        <span>{{ $faq[0] }}</span>
                        <span class="faq-chevron grid size-9 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="chevron-right" class="rotate-90" size="size-4" /></span>
                    </summary>
                    <div class="faq-answer border-t border-slate-200 px-5 py-5 text-sm leading-7 text-slate-500 dark:border-white/10 dark:text-slate-400 sm:px-6">{{ $faq[1] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
@push('head')
<style>
    .otp-flow { position: relative; min-height: 280px; overflow: hidden; border-radius: 1.4rem; padding: 2.25rem 1rem 1.2rem; background: radial-gradient(circle at 50% 20%, rgba(99,102,241,.26), transparent 42%), linear-gradient(180deg, rgba(15,23,42,.20), rgba(2,6,23,.38)); border: 1px solid rgba(255,255,255,.08); }
    .otp-flow-line { position: absolute; left: 17%; right: 17%; top: 83px; height: 3px; border-radius: 999px; background: linear-gradient(90deg, rgba(103,232,249,.2), rgba(129,140,248,.75), rgba(103,232,249,.2)); box-shadow: 0 0 20px rgba(103,232,249,.22); }
    .otp-flow-line::after { content: ''; position: absolute; inset: -5px 0; background: repeating-linear-gradient(90deg, transparent 0 18px, rgba(255,255,255,.18) 18px 21px); mask: linear-gradient(#000, #000); opacity: .45; }
    .otp-flow-node { display: flex; min-width: 0; flex-direction: column; align-items: center; text-align: center; opacity: .56; transform: translateY(4px) scale(.96); animation: otpNode 6s ease-in-out infinite; }
    .otp-flow-web { animation-delay: 1.7s; }
    .otp-flow-sms { animation-delay: 3.4s; }
    .otp-flow-icon { display: grid; width: 70px; height: 70px; place-items: center; border-radius: 22px; color: #cffafe; background: linear-gradient(145deg, rgba(99,102,241,.72), rgba(6,182,212,.35)); border: 1px solid rgba(165,243,252,.30); box-shadow: inset 0 1px rgba(255,255,255,.12), 0 12px 30px rgba(15,23,42,.28); }
    .otp-flow-label { margin-top: .75rem; font-size: .82rem; font-weight: 900; color: white; }
    .otp-flow-state { margin-top: .2rem; font-size: .62rem; font-weight: 700; color: rgba(207,250,254,.62); }
    .otp-flow-pulse { position: absolute; top: 76px; z-index: 20; width: 17px; height: 17px; border-radius: 999px; background: #67e8f9; border: 4px solid rgba(255,255,255,.35); box-shadow: 0 0 0 7px rgba(103,232,249,.10), 0 0 22px rgba(103,232,249,.8); opacity: 0; }
    .otp-flow-pulse-a { left: 17%; animation: otpTravelA 6s ease-in-out infinite; }
    .otp-flow-pulse-b { left: 49%; animation: otpTravelB 6s ease-in-out infinite; }
    .otp-sms-card { margin: 1.45rem auto 0; display: flex; width: min(100%, 330px); align-items: center; gap: .8rem; border-radius: 1rem; padding: .8rem .95rem; background: rgba(15,23,42,.62); border: 1px solid rgba(103,232,249,.20); box-shadow: 0 12px 30px rgba(2,6,23,.2); opacity: .35; transform: translateY(8px); animation: otpSmsCard 6s ease-in-out infinite; }
    .otp-live-dot { width: 9px; height: 9px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 6px rgba(52,211,153,.12); animation: otpLive 1.15s ease-in-out infinite; }
    @keyframes otpNode { 0%, 18%, 100% { opacity: .56; transform: translateY(4px) scale(.96); } 24%, 42% { opacity: 1; transform: translateY(0) scale(1.04); } 48%, 94% { opacity: .7; transform: translateY(0) scale(1); } }
    @keyframes otpTravelA { 0%, 17% { left: 17%; opacity: 0; } 22% { opacity: 1; } 40% { left: 49%; opacity: 1; } 45%, 100% { left: 49%; opacity: 0; } }
    @keyframes otpTravelB { 0%, 45% { left: 49%; opacity: 0; } 50% { opacity: 1; } 68% { left: 81%; opacity: 1; } 73%, 100% { left: 81%; opacity: 0; } }
    @keyframes otpSmsCard { 0%, 61%, 100% { opacity: .35; transform: translateY(8px); } 70%, 92% { opacity: 1; transform: translateY(0); box-shadow: 0 14px 38px rgba(34,211,238,.12); } }
    @keyframes otpLive { 50% { transform: scale(.72); opacity: .55; } }
    @media (max-width: 420px) { .otp-flow { min-height: 260px; padding-inline: .7rem; } .otp-flow-icon { width: 58px; height: 58px; border-radius: 18px; } .otp-flow-line { top: 77px; } .otp-flow-pulse { top: 70px; } .otp-flow-state { font-size: .55rem; } }
    @media (prefers-reduced-motion: reduce) { .otp-flow-node, .otp-flow-pulse, .otp-sms-card, .otp-live-dot { animation: none !important; } .otp-flow-node, .otp-sms-card { opacity: 1; transform: none; } .otp-flow-pulse { display: none; } }
</style>
@endpush
@endsection
