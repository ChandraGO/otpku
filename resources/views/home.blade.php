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
                    <img src="/illustrations/otp-hero.svg" alt="Ilustrasi verifikasi OTP" class="mt-4 w-full" loading="eager">
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
@endsection
