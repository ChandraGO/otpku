@extends('layouts.guest')
<?php $title = 'Verifikasi Email'; $robots = 'noindex,nofollow'; ?>
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12">
    <div
        class="card w-full p-6 text-center sm:p-8"
        data-otp-timer
        data-resend-remaining="{{ (int) ($otpStatus['resend_remaining'] ?? 0) }}"
        data-expires-at="{{ (int) ($otpStatus['expires_at'] ?? 0) }}"
    >
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-400/10 text-brand-600 dark:text-brand-300"><x-icon name="mail" size="size-7" /></span>
        <h1 class="mt-5 text-2xl font-black">Verifikasi email</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Masukkan kode 6 digit yang dikirim ke <strong>{{ auth()->user()->email }}</strong>.</p>
        <p class="mt-2 text-xs leading-5 text-slate-500">Periksa kotak masuk, tab promosi, atau folder spam.</p>

        <x-flash />

        <div class="mt-5 grid grid-cols-2 gap-3 text-left">
            <div class="card-soft p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Masa berlaku</div>
                <div class="mt-1 font-black" data-otp-expiry>--:--</div>
            </div>
            <div class="card-soft p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Kirim ulang</div>
                <div class="mt-1 font-black" data-otp-resend>Siap</div>
            </div>
        </div>

        <form class="mt-6" method="post" action="{{ route('verification.verify') }}">
            @csrf
            <input class="input text-center text-2xl font-black tracking-[.45em]" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <button class="btn-primary mt-4 w-full">Verifikasi email</button>
        </form>

        <form class="mt-3" method="post" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-50" data-otp-resend-button @disabled(($otpStatus['resend_remaining'] ?? 0) > 0)>
                Kirim ulang kode
            </button>
        </form>

        <div class="mt-6 rounded-2xl border border-slate-200 p-4 text-left text-xs leading-5 text-slate-500 dark:border-white/10 dark:text-slate-400">
            <div class="font-bold text-slate-700 dark:text-slate-200">Kode belum diterima?</div>
            <p class="mt-1">Tunggu sampai hitung mundur selesai, lalu kirim ulang. Jika tetap gagal, hubungi dukungan:</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a class="filter-chip" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                @if($supportWhatsapp)
                    <a class="filter-chip" href="https://wa.me/{{ $supportWhatsapp }}" target="_blank" rel="noopener">WhatsApp {{ $supportWhatsapp }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
