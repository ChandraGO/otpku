@extends('layouts.guest')
@php($title = 'Login')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="mx-auto flex min-h-[calc(100vh-8rem)] max-w-md items-center px-4 py-12">
    <div class="card w-full p-6 sm:p-8">
        <h1 class="text-2xl font-black">Masuk ke akun</h1>
        <p class="mt-2 text-sm text-slate-500">Gunakan email atau username Anda.</p>
        <x-flash />
        <form class="mt-7 space-y-4" method="post" action="{{ route('login') }}">
            @csrf
            <div>
                <label class="label">Email / username</label>
                <input class="input" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
            </div>
            <div>
                <div class="flex justify-between">
                    <label class="label">Password</label>
                    <a class="text-xs font-semibold text-brand-600 dark:text-brand-300" href="{{ route('password.request') }}">Lupa password?</a>
                </div>
                <x-password-input name="password" autocomplete="current-password" required />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-500">
                <input class="rounded" type="checkbox" name="remember" value="1"> Ingat saya
            </label>
            <button class="btn-primary w-full">Login</button>
        </form>
        <p class="mt-6 text-center text-sm text-slate-500">Belum punya akun? <a class="font-semibold text-brand-600 dark:text-brand-300" href="{{ route('register') }}">Daftar</a></p>
    </div>
</section>
@endsection
