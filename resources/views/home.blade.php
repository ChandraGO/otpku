@extends('layouts.guest')
@php($title = $siteName)
@section('content')
<section class="hero-grid relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_10%,rgba(34,211,238,.16),transparent_38%)]"></div>
    <div class="relative mx-auto grid min-h-[720px] max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2">
        <div>
            <span class="badge bg-brand-400/10 text-brand-700 dark:text-brand-300">Aktivasi OTP terpadu</span>
            <h1 class="mt-6 max-w-3xl text-4xl font-black leading-tight tracking-tight sm:text-6xl">Terima kode OTP dengan alur yang cepat, transparan, dan terukur.</h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-400">Pilih negara dan layanan, bayar dari saldo, lalu pantau nomor serta kode OTP dari satu dashboard. Harga, stok, dan status tersinkron dari provider.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth<a href="{{ route('services.index') }}" class="btn-primary">Pilih layanan</a>@else<a href="{{ route('register') }}" class="btn-primary">Buat akun</a><a href="{{ route('login') }}" class="btn-secondary">Masuk</a>@endauth
                <a href="{{ route('pricing') }}" class="btn-secondary">Lihat harga</a>
            </div>
            <p class="mt-5 text-xs leading-5 text-slate-500">Hanya untuk penggunaan yang sah. Pengguna wajib mengikuti ketentuan aplikasi tujuan dan hukum yang berlaku.</p>
        </div>
        <div class="card relative overflow-hidden p-5 sm:p-7">
            <div class="mb-5 flex items-center justify-between"><div><p class="text-xs uppercase tracking-[.2em] text-slate-500">Pratinjau layanan</p><h2 class="mt-1 text-xl font-bold">Stok tersedia</h2></div><span class="size-3 rounded-full bg-emerald-400 shadow-[0_0_20px_rgba(52,211,153,.8)]"></span></div>
            <div class="space-y-3">
                @forelse($popularPrices as $price)
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-white/10">
                        <div class="min-w-0"><div class="truncate font-semibold">{{ $price->service?->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $price->country?->name }} · {{ $price->operator_name ?: 'Semua operator' }} · Stok {{ $price->stock }}</div></div>
                        <div class="ml-4 whitespace-nowrap font-black text-brand-600 dark:text-brand-300">Rp {{ number_format((float) $price->sell_price, 0, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-white/10">Katalog akan tampil setelah admin mengisi API key dan menjalankan sinkronisasi.</div>
                @endforelse
            </div>
        </div>
    </div>
</section>
<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><div class="text-3xl font-black">{{ number_format($serviceCount) }}+</div><div class="mt-2 text-sm text-slate-500">Layanan tersinkron</div></div>
        <div class="stat"><div class="text-3xl font-black">{{ number_format($countryCount) }}+</div><div class="mt-2 text-sm text-slate-500">Negara tersedia</div></div>
        <div class="stat"><div class="text-3xl font-black">Server-side</div><div class="mt-2 text-sm text-slate-500">Kunci API tidak dikirim ke browser</div></div>
        <div class="stat"><div class="text-3xl font-black">Realtime</div><div class="mt-2 text-sm text-slate-500">Polling status dan masa berlaku</div></div>
    </div>
    <div class="mt-20 grid gap-8 lg:grid-cols-3">
        @foreach([['1','Pilih layanan','Cari aplikasi, negara, operator, harga, dan stok dari katalog.'],['2','Bayar dari saldo','Saldo dipotong sekali dengan kunci idempotensi untuk mencegah transaksi ganda.'],['3','Terima OTP','Nomor, status, pesan, kode OTP, dan waktu kedaluwarsa tampil di halaman pesanan.']] as $step)
            <article class="card p-6"><span class="grid size-10 place-items-center rounded-xl bg-brand-400 font-black text-slate-950">{{ $step[0] }}</span><h3 class="mt-5 text-lg font-bold">{{ $step[1] }}</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ $step[2] }}</p></article>
        @endforeach
    </div>
    @if($announcements->isNotEmpty())
        <div class="mt-20"><h2 class="text-2xl font-black">Pengumuman</h2><div class="mt-5 grid gap-4 lg:grid-cols-3">@foreach($announcements as $item)<article class="card p-5"><div class="flex justify-between gap-3"><h3 class="font-bold">{{ $item->title }}</h3><x-status :value="$item->type" /></div><p class="mt-3 line-clamp-4 whitespace-pre-line text-sm leading-6 text-slate-500">{{ $item->body }}</p></article>@endforeach</div></div>
    @endif
</section>
@endsection
