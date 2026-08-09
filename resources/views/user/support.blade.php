@extends('layouts.app')
@php
    $title = 'Tiket & Bantuan';
    $supportNumber = preg_replace('/\D+/', '', (string) ($siteSupportWhatsapp ?? ''));
    $supportMessage = urlencode('Halo '.$siteName.', saya membutuhkan bantuan terkait akun OTP.');
@endphp
@section('content')
<div>
    <h1 class="section-title">Tiket & Bantuan</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Pilih topik agar kami arahkan ke halaman yang paling relevan. Jika masalah Anda tidak ada di daftar, gunakan WhatsApp dukungan.</p>
</div>

<div class="mt-7 grid gap-6 lg:grid-cols-[1fr_360px]" x-data="{ topic: null }">
    <section class="card p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-black">Pilih topik bantuan</h2>
                <p class="mt-1 text-sm text-slate-500">Klik salah satu topik untuk melihat langkah berikutnya.</p>
            </div>
            <button type="button" class="btn-secondary !px-3 !py-2 text-xs" x-show="topic" x-cloak @click="topic = null">Reset</button>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach([
                ['orders','Pesanan & OTP','Nomor belum muncul, OTP terlambat, kirim ulang, atau pembatalan.'],
                ['topup','Isi Saldo & Pembayaran','Invoice, QRIS, virtual account, atau saldo belum masuk.'],
                ['wallet','Saldo & Mutasi','Pengecekan saldo, pengembalian dana, atau transaksi dompet.'],
                ['api','Integrasi API','Permintaan akses API pelanggan atau webhook.'],
            ] as $item)
                <button
                    type="button"
                    @click="topic = '{{ $item[0] }}'"
                    :aria-pressed="topic === '{{ $item[0] }}'"
                    class="card-soft group p-5 text-left transition hover:border-violet-300 hover:bg-violet-50/60 dark:hover:border-violet-400/30 dark:hover:bg-violet-500/[.06]"
                    :class="topic === '{{ $item[0] }}' ? 'border-violet-400 bg-violet-50 ring-2 ring-violet-500/10 dark:border-violet-400/50 dark:bg-violet-500/10' : ''"
                >
                    <span class="grid size-11 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon :name="$item[0]" /></span>
                    <span class="mt-4 block font-black">{{ $item[1] }}</span>
                    <span class="mt-2 block text-sm leading-6 text-slate-500">{{ $item[2] }}</span>
                    <span class="mt-4 inline-flex items-center gap-1 text-xs font-black text-violet-600 dark:text-violet-300">Pilih topik <x-icon name="arrow-right" size="size-3.5" /></span>
                </button>
            @endforeach
        </div>
    </section>

    <aside class="card p-6 lg:sticky lg:top-24 lg:self-start">
        <div x-show="!topic">
            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-icon name="ticket" size="size-6" /></span>
            <h2 class="mt-5 text-xl font-black">Tidak menemukan topik?</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Pilih salah satu topik terlebih dahulu bila sesuai. Untuk kendala di luar daftar, Anda dapat langsung menghubungi dukungan melalui WhatsApp.</p>
            @if(filled($supportNumber))
                <a target="_blank" rel="noopener" href="https://wa.me/{{ $supportNumber }}?text={{ $supportMessage }}" class="btn-primary mt-6 w-full">Chat melalui WhatsApp <x-icon name="arrow-right" size="size-4" /></a>
            @else
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">Nomor WhatsApp dukungan belum diisi oleh administrator.</div>
            @endif
        </div>

        <div x-show="topic === 'orders'" x-cloak>
            <span class="grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="orders" size="size-6" /></span>
            <h2 class="mt-5 text-xl font-black">Pesanan & OTP</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Buka riwayat pesanan, pilih transaksi yang bermasalah, lalu cek status nomor, SMS, OTP, masa berlaku, dan aksi kirim ulang/batal yang tersedia.</p>
            <a href="{{ route('orders.index') }}" class="btn-primary mt-6 w-full">Buka Pesanan & Riwayat <x-icon name="arrow-right" size="size-4" /></a>
        </div>

        <div x-show="topic === 'topup'" x-cloak>
            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-icon name="topup" size="size-6" /></span>
            <h2 class="mt-5 text-xl font-black">Isi Saldo & Pembayaran</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Periksa invoice aktif, metode pembayaran, status QRIS/VA, dan waktu transaksi. Jika invoice sudah kedaluwarsa, buat transaksi baru.</p>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.topups.index') }}" class="btn-primary mt-6 w-full">Buka Pembayaran <x-icon name="arrow-right" size="size-4" /></a>
            @else
                <a href="{{ route('topups.index') }}" class="btn-primary mt-6 w-full">Buka Isi Saldo <x-icon name="arrow-right" size="size-4" /></a>
            @endif
        </div>

        <div x-show="topic === 'wallet'" x-cloak>
            <span class="grid size-12 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="wallet" size="size-6" /></span>
            <h2 class="mt-5 text-xl font-black">Saldo & Mutasi</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Gunakan mutasi untuk mencocokkan debit, kredit, refund, dan perubahan saldo berdasarkan waktu transaksi.</p>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" class="btn-primary mt-6 w-full">Buka Pengguna & Saldo <x-icon name="arrow-right" size="size-4" /></a>
            @else
                <a href="{{ route('wallet.index') }}" class="btn-primary mt-6 w-full">Buka Mutasi Saldo <x-icon name="arrow-right" size="size-4" /></a>
            @endif
        </div>

        <div x-show="topic === 'api'" x-cloak>
            <span class="grid size-12 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300"><x-icon name="api" size="size-6" /></span>
            <h2 class="mt-5 text-xl font-black">Integrasi API</h2>
            <p class="mt-3 text-sm leading-6 text-slate-500">Periksa API key, endpoint, contoh request, status HTTP, serta batas penggunaan sebelum menghubungkan bot atau sistem eksternal.</p>
            <a href="{{ route('api.docs') }}" class="btn-primary mt-6 w-full">Buka Dokumentasi API <x-icon name="arrow-right" size="size-4" /></a>
        </div>
    </aside>
</div>
@endsection
