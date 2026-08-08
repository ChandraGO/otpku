@extends('layouts.guest')
@php($title = 'Daftar')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-xl px-4 py-12">
    <div class="card p-6 sm:p-8">
        <h1 class="text-2xl font-black">Buat akun baru</h1>
        
        <x-flash />
        <form class="mt-7 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('register') }}">
            @csrf
            <div><label class="label">Nama pengguna</label><input class="input" name="username" value="{{ old('username') }}" autocomplete="username" required></div>
            <div><label class="label">Nama</label><input class="input" name="name" value="{{ old('name') }}" autocomplete="name" required></div>
            <div><label class="label">WhatsApp aktif</label><input class="input" name="whatsapp" value="{{ old('whatsapp') }}" inputmode="tel" autocomplete="tel" placeholder="62812..." required></div>
            <div><label class="label">Email</label><input class="input" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
            <div><label class="label">Kata sandi</label><x-password-input name="password" autocomplete="new-password" required /></div>
            <div><label class="label">Ulangi kata sandi</label><x-password-input name="password_confirmation" autocomplete="new-password" required /></div>
            <label class="flex items-start gap-2 text-sm text-slate-500 sm:col-span-2">
                <input class="mt-1 rounded" type="checkbox" name="terms" value="1" required>
                <span>Saya menyetujui <a class="text-brand-600 dark:text-brand-300" href="{{ route('terms') }}">syarat penggunaan</a> dan hanya akan memakai layanan untuk aktivitas yang sah.</span>
            </label>
            <button class="btn-primary sm:col-span-2">Daftar & kirim OTP</button>
        </form>
    </div>
</section>
@endsection
