@extends('layouts.guest')
<?php $title = 'Daftar'; $robots = 'noindex,nofollow'; ?>
@section('content')
<section class="mx-auto max-w-xl px-4 py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="text-2xl font-black">Buat akun baru</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Kode verifikasi akan dikirim ke email setelah pendaftaran.</p>
        <x-flash />

        <form class="mt-7 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('register') }}">
            @csrf
            <div>
                <label class="label">Username</label>
                <input class="input" name="username" value="{{ old('username') }}" autocomplete="username" required>
            </div>
            <div>
                <label class="label">Nama</label>
                <input class="input" name="name" value="{{ old('name') }}" autocomplete="name" required>
            </div>
            <div>
                <label class="label">WhatsApp aktif</label>
                <input class="input" name="whatsapp" value="{{ old('whatsapp') }}" inputmode="tel" autocomplete="tel" placeholder="Contoh: 628123456789" required>
                <p class="mt-1 text-xs text-slate-500">Nomor 08, 8, +62, dan 62 akan otomatis disesuaikan.</p>
            </div>
            <div>
                <label class="label">Email</label>
                <input class="input" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>
            <div>
                <label class="label">Password</label>
                <div class="relative">
                    <input id="register-password" class="input !pr-12" name="password" type="password" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle="register-password" aria-label="Tampilkan password" aria-pressed="false">
                        <x-icon name="eye" size="size-5" data-eye-open />
                        <x-icon name="eye-off" size="size-5" class="hidden" data-eye-closed />
                    </button>
                </div>
                <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter, huruf besar, huruf kecil, dan angka.</p>
            </div>
            <div>
                <label class="label">Ulangi password</label>
                <div class="relative">
                    <input id="register-password-confirmation" class="input !pr-12" name="password_confirmation" type="password" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle="register-password-confirmation" aria-label="Tampilkan konfirmasi password" aria-pressed="false">
                        <x-icon name="eye" size="size-5" data-eye-open />
                        <x-icon name="eye-off" size="size-5" class="hidden" data-eye-closed />
                    </button>
                </div>
            </div>
            <label class="sm:col-span-2 flex items-start gap-2 text-sm text-slate-500">
                <input class="mt-1 rounded" type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                <span>Saya menyetujui <a class="text-brand-600 dark:text-brand-300" href="{{ route('terms') }}">syarat penggunaan</a> dan hanya akan memakai layanan untuk aktivitas yang sah.</span>
            </label>
            <button class="btn-primary sm:col-span-2">Daftar dan kirim kode</button>
        </form>
    </div>
</section>
@endsection
