@extends('layouts.guest')
<?php $title = 'Masuk'; $robots = 'noindex,nofollow'; ?>
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-black">Masuk ke akun</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Gunakan email atau username Anda.</p>
        <x-flash />

        <form class="mt-7 space-y-4" method="post" action="{{ route('login') }}">
            @csrf
            <div>
                <label class="label">Email atau username</label>
                <input class="input" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
            </div>
            <div>
                <div class="flex justify-between">
                    <label class="label">Password</label>
                    <a class="text-xs font-semibold text-brand-600 dark:text-brand-300" href="{{ route('password.request') }}">Lupa password?</a>
                </div>
                <div class="relative">
                    <input id="login-password" class="input !pr-12" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-password-toggle="login-password" aria-label="Tampilkan password" aria-pressed="false">
                        <x-icon name="eye" size="size-5" data-eye-open />
                        <x-icon name="eye-off" size="size-5" class="hidden" data-eye-closed />
                    </button>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-500">
                <input class="rounded" type="checkbox" name="remember" value="1" @checked(old('remember'))>
                Ingat saya
            </label>
            <button class="btn-primary w-full">Masuk</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">Belum punya akun? <a class="font-semibold text-brand-600 dark:text-brand-300" href="{{ route('register') }}">Daftar</a></p>
    </div>
</section>
@endsection
