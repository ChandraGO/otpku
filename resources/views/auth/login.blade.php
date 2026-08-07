@extends('layouts.guest')
@php($title = 'Login')
@php($robots = 'noindex,nofollow')
@section('content')
<section class="page-grid mx-auto flex min-h-[calc(100vh-8rem)] items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-5 text-center"><span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Selamat datang kembali</span></div>
        <div class="card w-full p-6 sm:p-8">
            <div class="flex items-start gap-4"><span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-500 text-white shadow-lg"><x-icon name="user" size="size-6" /></span><div><h1 class="text-2xl font-black">Masuk ke akun</h1><p class="mt-1 text-sm leading-6 text-slate-500">Gunakan email atau username Anda.</p></div></div>
            <div class="mt-6"><x-flash /></div>
            <a href="{{ route('login.github') }}" class="mt-6 flex w-full items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-500/20 dark:border-white/10 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100">
                <x-icon name="github" size="size-5" /> Masuk dengan GitHub
            </a>
            @error('github')<p class="mt-2 text-center text-xs font-semibold text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
            <div class="my-6 flex items-center gap-3 text-[11px] font-bold uppercase tracking-[.16em] text-slate-400"><span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span><span>atau</span><span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span></div>
            <form class="space-y-4" method="post" action="{{ route('login') }}">
                @csrf
                <div><label class="label" for="login">Email / username</label><input id="login" class="input @error('login') !border-rose-400 !ring-4 !ring-rose-500/10 @enderror" name="login" value="{{ old('login') }}" autocomplete="username" placeholder="nama@email.com atau username" required autofocus>@error('login')<p class="mt-2 text-xs font-semibold text-rose-600 dark:text-rose-300">Periksa kembali email/username dan password Anda.</p>@enderror</div>
                <div><div class="flex justify-between gap-3"><label class="label" for="password">Password</label><a class="text-xs font-semibold text-brand-600 dark:text-brand-300" href="{{ route('password.request') }}">Lupa password?</a></div><x-password-input id="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required /></div>
                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 p-3 text-sm text-slate-500 dark:border-white/10"><input class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Ingat saya di perangkat ini</span></label>
                <button class="btn-primary w-full py-3">Login <x-icon name="arrow-right" size="size-4" /></button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-500">Belum punya akun? <a class="font-bold text-brand-600 dark:text-brand-300" href="{{ route('register') }}">Buat akun</a></p>
        </div>
    </div>
</section>
@endsection
