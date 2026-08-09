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
            <p class="mt-5 max-w-2xl text-base font-medium leading-7 text-slate-600 dark:text-slate-300 sm:text-lg sm:leading-8">
                Pilih layanan dan negara, terima nomor virtual, lalu pantau SMS serta kode OTP dari satu halaman dengan status transaksi yang jelas.
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
                <span class="inline-flex items-center gap-2"><span class="grid size-6 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/10"><x-icon name="check" size="size-4" /></span> Status pesanan waktu nyata</span>
            </div>
        </div>

        <div class="relative">
            <div class="absolute -inset-8 rounded-full bg-gradient-to-br from-violet-500/20 to-cyan-400/20 blur-3xl"></div>
            <div class="card relative overflow-hidden p-4 sm:p-6">
                <div class="rounded-[1.4rem] bg-gradient-to-br from-[#17233d] via-[#203252] to-[#152039] p-5 text-white sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">Verifikasi aman</div>
                            <h2 class="mt-2 text-2xl font-black">OTP diterima langsung</h2>
                            <p class="mt-2 max-w-sm text-xs leading-5 text-slate-300">Contoh alur verifikasi WhatsApp melalui nomor virtual.</p>
                        </div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl border border-white/10 bg-white/[.07]"><x-icon name="shield" size="size-6" /></span>
                    </div>

                    <div class="otp-stage mt-6" aria-label="Animasi alur pengguna meminta OTP, web memverifikasi, lalu SMS WhatsApp masuk">
                        <div class="otp-service-row" aria-label="Contoh layanan OTP">
                            <span class="otp-service-chip otp-service-chip-active"><span class="otp-service-dot bg-emerald-400"></span> WhatsApp</span>
                            <span class="otp-service-chip"><span class="otp-service-dot bg-sky-400"></span> Telegram</span>
                            <span class="otp-service-chip"><span class="otp-service-dot bg-amber-300"></span> Google</span>
                        </div>

                        <div class="otp-flow-clean">
                            <div class="otp-step otp-step-user">
                                <span class="otp-step-number">01</span>
                                <span class="otp-step-icon"><x-icon name="user" size="size-6" /></span>
                                <span class="otp-step-title">User</span>
                                <span class="otp-step-copy">Minta OTP</span>
                            </div>

                            <div class="otp-connector otp-connector-a" aria-hidden="true"><span></span></div>

                            <div class="otp-step otp-step-web">
                                <span class="otp-step-number">02</span>
                                <span class="otp-step-icon"><x-icon name="globe" size="size-6" /></span>
                                <span class="otp-step-title">Web</span>
                                <span class="otp-step-copy">Verifikasi</span>
                            </div>

                            <div class="otp-connector otp-connector-b" aria-hidden="true"><span></span></div>

                            <div class="otp-step otp-step-sms">
                                <span class="otp-step-number">03</span>
                                <span class="otp-step-icon"><x-icon name="mail" size="size-6" /></span>
                                <span class="otp-step-title">SMS</span>
                                <span class="otp-step-copy">OTP masuk</span>
                            </div>
                        </div>

                        <div class="otp-message">
                            <div class="otp-message-head">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-400/15 text-emerald-200"><x-icon name="check" size="size-5" /></span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-black text-white">WhatsApp</div>
                                        <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[.14em] text-cyan-300">SMS verifikasi diterima</div>
                                    </div>
                                </div>
                                <span class="otp-live-pill"><span></span> Live</span>
                            </div>
                            <div class="otp-message-body">
                                <p class="text-xs leading-5 text-slate-300">Kode verifikasi WhatsApp Anda:</p>
                                <div class="mt-2 flex items-end justify-between gap-3">
                                    <strong class="font-mono text-2xl font-black tracking-[.22em] text-white sm:text-3xl">842 193</strong>
                                    <span class="text-[10px] font-semibold text-slate-400">Jangan bagikan kode ini</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $serviceCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Layanan</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $countryCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Negara</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $userCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pengguna</div></div>
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
    .otp-stage { overflow: hidden; border: 1px solid rgba(148,163,184,.13); border-radius: 1.25rem; background: linear-gradient(180deg, rgba(15,23,42,.42), rgba(2,6,23,.25)); padding: .85rem; }
    .otp-service-row { display: flex; flex-wrap: wrap; gap: .45rem; padding: .1rem .1rem .85rem; border-bottom: 1px solid rgba(148,163,184,.12); }
    .otp-service-chip { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(148,163,184,.13); border-radius: 999px; background: rgba(15,23,42,.38); padding: .38rem .6rem; font-size: .62rem; font-weight: 800; color: rgba(226,232,240,.66); }
    .otp-service-chip-active { border-color: rgba(52,211,153,.25); background: rgba(16,185,129,.09); color: #d1fae5; }
    .otp-service-dot { width: .42rem; height: .42rem; border-radius: 999px; box-shadow: 0 0 0 4px rgba(255,255,255,.025); }
    .otp-flow-clean { display: grid; grid-template-columns: minmax(0,1fr) 38px minmax(0,1fr) 38px minmax(0,1fr); align-items: center; gap: .45rem; padding: 1rem .1rem .85rem; }
    .otp-step { position: relative; min-width: 0; border: 1px solid rgba(148,163,184,.12); border-radius: 1rem; background: rgba(30,41,59,.38); padding: .72rem .45rem .68rem; text-align: center; box-shadow: inset 0 1px 0 rgba(255,255,255,.025); opacity: .62; animation: otpCleanStep 6.6s ease-in-out infinite; }
    .otp-step-web { animation-delay: 1.8s; }
    .otp-step-sms { animation-delay: 3.6s; }
    .otp-step-number { position: absolute; left: .45rem; top: .4rem; font-size: .48rem; font-weight: 900; letter-spacing: .08em; color: rgba(148,163,184,.55); }
    .otp-step-icon { margin: 0 auto; display: grid; width: 2.85rem; height: 2.85rem; place-items: center; border-radius: .9rem; color: #cffafe; background: linear-gradient(145deg, rgba(99,102,241,.55), rgba(6,182,212,.20)); border: 1px solid rgba(165,243,252,.15); }
    .otp-step-title { display: block; margin-top: .55rem; font-size: .72rem; font-weight: 900; color: white; }
    .otp-step-copy { display: block; margin-top: .12rem; font-size: .54rem; font-weight: 700; color: rgba(203,213,225,.62); white-space: nowrap; }
    .otp-connector { position: relative; height: 2px; overflow: visible; border-radius: 999px; background: rgba(148,163,184,.16); }
    .otp-connector::after { content: ''; position: absolute; left: 0; top: 0; width: 0; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #818cf8, #67e8f9); box-shadow: 0 0 12px rgba(103,232,249,.45); animation: otpCleanConnector 6.6s ease-in-out infinite; }
    .otp-connector-b::after { animation-delay: 2.15s; }
    .otp-connector span { position: absolute; right: -1px; top: 50%; width: 6px; height: 6px; border-top: 2px solid rgba(103,232,249,.55); border-right: 2px solid rgba(103,232,249,.55); transform: translateY(-50%) rotate(45deg); }
    .otp-message { margin-top: .25rem; overflow: hidden; border: 1px solid rgba(103,232,249,.16); border-radius: 1rem; background: rgba(15,23,42,.62); box-shadow: 0 14px 38px rgba(2,6,23,.17); opacity: .5; transform: translateY(6px); animation: otpCleanMessage 6.6s ease-in-out infinite; }
    .otp-message-head { display: flex; align-items: center; justify-content: space-between; gap: .7rem; padding: .75rem .8rem; border-bottom: 1px solid rgba(148,163,184,.10); }
    .otp-message-body { padding: .8rem; }
    .otp-live-pill { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; background: rgba(16,185,129,.10); padding: .28rem .5rem; font-size: .52rem; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; color: #a7f3d0; }
    .otp-live-pill span { width: .4rem; height: .4rem; border-radius: 999px; background: #34d399; box-shadow: 0 0 0 4px rgba(52,211,153,.10); animation: otpLive 1.2s ease-in-out infinite; }
    @keyframes otpCleanStep { 0%, 16%, 100% { opacity:.62; transform:translateY(0); border-color:rgba(148,163,184,.12); } 22%, 39% { opacity:1; transform:translateY(-2px); border-color:rgba(103,232,249,.28); box-shadow:0 12px 26px rgba(8,145,178,.08), inset 0 1px rgba(255,255,255,.04); } 45%, 92% { opacity:.74; transform:translateY(0); } }
    @keyframes otpCleanConnector { 0%, 20% { width:0; opacity:0; } 25% { opacity:1; } 42%, 82% { width:100%; opacity:1; } 90%, 100% { width:100%; opacity:.28; } }
    @keyframes otpCleanMessage { 0%, 58%, 100% { opacity:.5; transform:translateY(6px); } 68%, 92% { opacity:1; transform:translateY(0); border-color:rgba(52,211,153,.24); } }
    @keyframes otpLive { 50% { transform:scale(.72); opacity:.55; } }
    @media (max-width: 420px) {
        .otp-stage { padding:.7rem; }
        .otp-flow-clean { grid-template-columns:minmax(0,1fr) 20px minmax(0,1fr) 20px minmax(0,1fr); gap:.25rem; }
        .otp-step { border-radius:.85rem; padding:.68rem .25rem .58rem; }
        .otp-step-icon { width:2.35rem; height:2.35rem; border-radius:.72rem; }
        .otp-step-title { font-size:.65rem; }
        .otp-step-copy { font-size:.48rem; }
        .otp-step-number { display:none; }
        .otp-service-chip { font-size:.56rem; padding:.34rem .5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .otp-step, .otp-connector::after, .otp-message, .otp-live-pill span { animation:none !important; }
        .otp-step, .otp-message { opacity:1; transform:none; }
        .otp-connector::after { width:100%; opacity:.65; }
    }
</style>
@endpush
@endsection
