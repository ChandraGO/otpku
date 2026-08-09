@extends('layouts.guest')
@php
    $title = $siteName;
    $otpSampleCodes = ['842 193', '517 406', '291 684'];
    $otpSamples = collect($otpPreviewServices ?? [])
        ->values()
        ->map(fn ($service, $index) => [
            'name' => (string) ($service['name'] ?? 'Layanan OTP'),
            'icon_url' => (string) ($service['icon_url'] ?? ''),
            'code' => $otpSampleCodes[$index % count($otpSampleCodes)],
        ])
        ->all();

    if ($otpSamples === []) {
        $otpSamples = [[
            'name' => 'Layanan OTP',
            'icon_url' => '',
            'code' => $otpSampleCodes[0],
        ]];
    }

    $firstOtpSample = $otpSamples[0];
@endphp
@section('content')
<section class="page-grid relative overflow-hidden">
    <div class="mx-auto grid min-h-[720px] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:py-24">
        <div class="relative z-10">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <span class="hero-live-dot size-2 rounded-full bg-emerald-400" aria-hidden="true"></span>
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
                    <a href="{{ route('services.index') }}" class="btn-primary hero-float-cta">Pilih layanan <x-icon name="arrow-right" size="size-4" /></a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary hero-float-cta">Mulai sekarang <x-icon name="arrow-right" size="size-4" /></a>
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
                            <p class="mt-2 max-w-sm text-xs leading-5 text-slate-300">Contoh alur OTP dari layanan populer melalui nomor virtual.</p>
                        </div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl border border-white/10 bg-white/[.07]"><x-icon name="shield" size="size-6" /></span>
                    </div>

                    <div class="otp-stage mt-6" aria-label="Animasi alur pengguna meminta OTP, web memverifikasi, lalu SMS layanan masuk">
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

                        <div class="otp-message" data-otp-preview>
                            <div class="otp-message-head">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="otp-service-logo" aria-hidden="true">
                                        @foreach($otpSamples as $index => $sample)
                                            @if(filled($sample['icon_url']))
                                                <img
                                                    src="{{ $sample['icon_url'] }}"
                                                    alt=""
                                                    class="otp-service-logo-item {{ $index === 0 ? 'is-active' : '' }}"
                                                    data-otp-logo
                                                    data-service-name="{{ $sample['name'] }}"
                                                    data-code="{{ $sample['code'] }}"
                                                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                                    decoding="async"
                                                    referrerpolicy="no-referrer"
                                                >
                                            @else
                                                <span
                                                    class="otp-service-logo-item otp-service-logo-fallback {{ $index === 0 ? 'is-active' : '' }}"
                                                    data-otp-logo
                                                    data-service-name="{{ $sample['name'] }}"
                                                    data-code="{{ $sample['code'] }}"
                                                ></span>
                                            @endif
                                        @endforeach
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-black text-white" data-otp-service-name>{{ $firstOtpSample['name'] }}</div>
                                        <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[.14em] text-cyan-300">SMS verifikasi diterima</div>
                                    </div>
                                </div>
                                <span class="otp-live-pill"><span></span> Live</span>
                            </div>
                            <div class="otp-message-body">
                                <p class="text-xs leading-5 text-slate-300">Kode verifikasi <span data-otp-service-inline>{{ $firstOtpSample['name'] }}</span> Anda:</p>
                                <div class="mt-2 flex items-end justify-between gap-3">
                                    <strong class="font-mono text-2xl font-black tracking-[.22em] text-white sm:text-3xl" data-otp-code>{{ $firstOtpSample['code'] }}</strong>
                                    <span class="text-[10px] font-semibold text-slate-400">Jangan bagikan kode ini</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $serviceCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Layanan</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-to="{{ (int) $countryCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">0+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Negara</div></div>
                    <div class="card-soft p-3 text-center"><div data-count-from="50" data-count-to="{{ (int) $userCount }}" data-count-suffix="+" class="text-lg font-black text-violet-600 dark:text-violet-300">50+</div><div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pengguna</div></div>
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
        <article class="card p-7 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="wallet" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Harga Transparan</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Harga ditampilkan jelas dalam Rupiah sebelum pesanan dibuat.</p>
        </article>
        <article class="card p-7 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="bolt" size="size-6" /></span>
            <h3 class="mt-5 text-xl font-black">Pengiriman Cepat</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Nomor, status, isi SMS, kode OTP, dan masa berlaku ditampilkan langsung pada halaman pesanan.</p>
        </article>
        <article class="card p-7 text-center">
            <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-icon name="shield" size="size-6" /></span>
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
    <div class="mx-auto max-w-3xl text-center">
        <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">3 langkah sederhana</span>
        <h2 class="section-title mt-5">Cara kerja pemesanan OTP</h2>
        <p class="mt-4 text-sm leading-7 text-slate-500 dark:text-slate-400 sm:text-base">Mulai dari memilih layanan hingga menerima kode OTP, seluruh proses dapat dipantau dari satu alur yang jelas.</p>
    </div>

    <div class="relative mt-12">
        <div class="absolute left-[16.66%] right-[16.66%] top-10 hidden border-t-2 border-dashed border-slate-200 dark:border-white/10 lg:block" aria-hidden="true"></div>
        <div class="relative grid gap-5 lg:grid-cols-3">
            @foreach([
                ['1', 'services', 'Pilih layanan', 'Pilih aplikasi yang ingin diverifikasi, tentukan negara atau operator, lalu pilih harga dengan stok yang masih tersedia.', 'violet'],
                ['2', 'wallet', 'Bayar dari saldo', 'Harga pesanan dipotong langsung dari saldo Rupiah akun setelah Anda menekan tombol Pesan. Jika saldo kurang, isi saldo terlebih dahulu.', 'cyan'],
                ['3', 'mail', 'Terima OTP', 'Setelah nomor virtual aktif, gunakan nomor tersebut pada layanan tujuan. SMS dan kode OTP yang masuk akan tampil pada halaman detail pesanan beserta status dan waktu kedaluwarsa.', 'emerald'],
            ] as $step)
                @php
                    $stepTone = match($step[4]) {
                        'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300',
                        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
                        default => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
                    };
                @endphp
                <article class="card group relative overflow-hidden p-7 transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                    <div class="flex items-center justify-between gap-4">
                        <span class="grid size-12 place-items-center rounded-2xl {{ $stepTone }}"><x-icon :name="$step[1]" size="size-6" /></span>
                        <span class="grid size-9 place-items-center rounded-full border border-slate-200 bg-white text-sm font-black text-slate-500 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300">{{ $step[0] }}</span>
                    </div>
                    <div class="mt-7 text-[10px] font-black uppercase tracking-[.18em] text-slate-400">Langkah {{ $step[0] }}</div>
                    <h3 class="mt-2 text-xl font-black">{{ $step[2] }}</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-500 dark:text-slate-400">{{ $step[3] }}</p>
                    @if($step[0] !== '3')
                        <span class="absolute -right-3 top-8 hidden size-7 place-items-center rounded-full border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-white/10 dark:bg-[#111a2d] lg:grid" aria-hidden="true"><x-icon name="chevron-right" size="size-4" /></span>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="border-y border-slate-200 bg-white py-20 dark:border-white/10 dark:bg-[#0a1020]" aria-labelledby="rating-home-title">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">Rating pengguna</span>
                    @if(($ratingCount ?? 0) > 0)
                        <span class="inline-flex items-center gap-1.5 text-sm font-black text-slate-700 dark:text-slate-200"><span class="text-amber-400">★</span> {{ number_format((float) $ratingAverage, 1, ',', '.') }} <span class="font-semibold text-slate-400">· {{ number_format((int) $ratingCount) }} review</span></span>
                    @endif
                </div>
                <h2 id="rating-home-title" class="section-title mt-4">Pengalaman pelanggan setelah transaksi</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-500 dark:text-slate-400">Review berasal dari akun yang sudah memiliki minimal satu transaksi OTP berstatus selesai.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(($homeRatings ?? collect())->count() > 1)
                    <button type="button" class="btn-secondary !p-2.5" data-rating-prev aria-label="Rating sebelumnya"><x-icon name="chevron-right" class="rotate-180" size="size-4" /></button>
                    <button type="button" class="btn-secondary !p-2.5" data-rating-next aria-label="Rating berikutnya"><x-icon name="chevron-right" size="size-4" /></button>
                @endif
                <a href="{{ route('ratings.index') }}" class="btn-secondary">Lihat semua rating <x-icon name="arrow-right" size="size-4" /></a>
            </div>
        </div>

        @if(($homeRatings ?? collect())->isNotEmpty())
            <div class="rating-home-track mt-9 flex gap-4 overflow-x-auto pb-2" data-rating-track>
                @foreach($homeRatings as $rating)
                    @php($avatarUrl = $rating->user?->emailAvatarUrl(96))
                    <article class="card rating-home-card flex min-w-[86%] snap-start flex-col p-6 sm:min-w-[380px] lg:min-w-[390px]">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                @if(filled($avatarUrl))
                                    <span class="size-11 shrink-0 overflow-hidden rounded-2xl bg-slate-100 dark:bg-white/5" data-rating-avatar>
                                        <img src="{{ $avatarUrl }}" alt="Foto {{ $rating->user?->name ?: 'pengguna' }}" class="h-full w-full object-cover" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.closest('[data-rating-avatar]')?.remove()">
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate font-black text-slate-950 dark:text-white">{{ $rating->user?->name ?: $rating->user?->username ?: 'Pengguna' }}</div>
                                    <div class="mt-1 text-[11px] font-semibold text-slate-400">{{ $rating->updated_at?->translatedFormat('d M Y · H:i') }}</div>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Terverifikasi</span>
                        </div>
                        <div class="mt-5 flex items-center gap-1" aria-label="{{ $rating->rating }} dari 5 bintang">
                            @for($star = 1; $star <= 5; $star++)
                                <span class="text-lg {{ $star <= $rating->rating ? 'text-amber-400' : 'text-slate-200 dark:text-slate-700' }}">★</span>
                            @endfor
                        </div>
                        <p class="mt-4 line-clamp-4 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $rating->review }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <div class="card mt-9 flex flex-col items-center p-9 text-center">
                <div class="grid size-14 place-items-center rounded-2xl bg-amber-100 text-2xl text-amber-600 dark:bg-amber-400/10 dark:text-amber-300">★</div>
                <h3 class="mt-4 text-xl font-black">Belum ada review pelanggan</h3>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">Review pertama dapat diberikan oleh pengguna setelah menyelesaikan minimal satu transaksi OTP.</p>
                <a href="{{ route('ratings.index') }}" class="btn-secondary mt-5">Buka halaman rating</a>
            </div>
        @endif
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
    .hero-float-cta { animation: heroFloatCta 3.2s ease-in-out infinite; will-change: transform; }
    .hero-float-cta:hover { animation-play-state: paused; transform: translateY(-3px); }
    .hero-live-dot { position: relative; flex: 0 0 auto; box-shadow: 0 0 0 0 rgba(52,211,153,.45); animation: heroLiveDot 1.35s ease-out infinite; }
    .hero-live-dot::after { content: ''; position: absolute; inset: -4px; border: 1px solid rgba(52,211,153,.30); border-radius: 999px; opacity: 0; animation: heroLiveRing 1.35s ease-out infinite; }
    @keyframes heroFloatCta { 0%,100% { transform: translateY(0); box-shadow: 0 10px 28px rgba(124,58,237,.16); } 50% { transform: translateY(-5px); box-shadow: 0 16px 34px rgba(124,58,237,.24); } }
    @keyframes heroLiveDot { 0% { box-shadow: 0 0 0 0 rgba(52,211,153,.38); } 70%,100% { box-shadow: 0 0 0 8px rgba(52,211,153,0); } }
    @keyframes heroLiveRing { 0% { transform: scale(.72); opacity:.75; } 70%,100% { transform: scale(1.65); opacity:0; } }
    .otp-stage { overflow: hidden; border: 1px solid rgba(148,163,184,.13); border-radius: 1.25rem; background: linear-gradient(180deg, rgba(15,23,42,.42), rgba(2,6,23,.25)); padding: .85rem; }
    .otp-flow-clean { display: grid; grid-template-columns: minmax(0,1fr) 38px minmax(0,1fr) 38px minmax(0,1fr); align-items: center; gap: .45rem; padding: .2rem .1rem .85rem; }
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
    .otp-service-logo { position: relative; display: grid; width: 2.65rem; height: 2.65rem; flex-shrink: 0; place-items: center; overflow: hidden; border: 1px solid rgba(255,255,255,.16); border-radius: .82rem; background: rgba(255,255,255,.96); box-shadow: 0 8px 22px rgba(2,6,23,.22); }
    .otp-service-logo-item { position: absolute; inset: .38rem; width: calc(100% - .76rem); height: calc(100% - .76rem); object-fit: contain; opacity: 0; transform: scale(.84); transition: opacity .2s ease, transform .2s ease; }
    .otp-service-logo-item.is-active { opacity: 1; transform: scale(1); }
    .otp-service-logo-fallback { inset: .48rem; width: calc(100% - .96rem); height: calc(100% - .96rem); border-radius: .45rem; background: linear-gradient(135deg, #7c3aed, #06b6d4); }
    .otp-message [data-otp-service-name], .otp-message [data-otp-service-inline], .otp-message [data-otp-code] { transition: opacity .16s ease, transform .16s ease; }
    .otp-message.otp-message-switching [data-otp-service-name], .otp-message.otp-message-switching [data-otp-service-inline], .otp-message.otp-message-switching [data-otp-code] { opacity: .25; transform: translateY(2px); }
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
    }
    .rating-home-track { scroll-snap-type:x mandatory; scrollbar-width:none; -ms-overflow-style:none; scroll-behavior:smooth; }
    .rating-home-track::-webkit-scrollbar { display:none; }
    .rating-home-card { scroll-snap-align:start; }
    @media (prefers-reduced-motion: reduce) {
        .hero-float-cta, .hero-live-dot, .hero-live-dot::after, .otp-step, .otp-connector::after, .otp-message, .otp-live-pill span { animation:none !important; }
        .otp-step, .otp-message { opacity:1; transform:none; }
        .otp-connector::after { width:100%; opacity:.65; }
    }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-otp-preview]');
    if (!root) return;

    const logos = Array.from(root.querySelectorAll('[data-otp-logo]'));
    if (logos.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const name = root.querySelector('[data-otp-service-name]');
    const inlineName = root.querySelector('[data-otp-service-inline]');
    const code = root.querySelector('[data-otp-code]');
    let index = 0;

    window.setInterval(() => {
        root.classList.add('otp-message-switching');

        window.setTimeout(() => {
            logos[index]?.classList.remove('is-active');
            index = (index + 1) % logos.length;
            const active = logos[index];
            active?.classList.add('is-active');

            const serviceName = active?.dataset.serviceName || 'Layanan OTP';
            const otpCode = active?.dataset.code || '842 193';
            if (name) name.textContent = serviceName;
            if (inlineName) inlineName.textContent = serviceName;
            if (code) code.textContent = otpCode;

            root.classList.remove('otp-message-switching');
        }, 170);
    }, 6600);
})();

(() => {
    const track = document.querySelector('[data-rating-track]');
    if (!track) return;
    const prev = document.querySelector('[data-rating-prev]');
    const next = document.querySelector('[data-rating-next]');
    const amount = () => {
        const card = track.querySelector('.rating-home-card');
        return card ? card.getBoundingClientRect().width + 16 : Math.max(280, track.clientWidth * .88);
    };
    prev?.addEventListener('click', () => track.scrollBy({ left: -amount(), behavior: 'smooth' }));
    next?.addEventListener('click', () => track.scrollBy({ left: amount(), behavior: 'smooth' }));
})();
</script>
@endpush
@endsection
