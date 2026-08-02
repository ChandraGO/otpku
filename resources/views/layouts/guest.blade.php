<!DOCTYPE html>
<html lang="id" class="dark" data-default-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $siteName }} — {{ $siteName }}</title>
    <meta name="description" content="{{ $description ?? $siteDescription }}">
    <meta name="robots" content="{{ $robots ?? 'index,follow' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $title ?? $siteName }}">
    <meta property="og:description" content="{{ $description ?? $siteDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>try{const t=localStorage.getItem('theme')||'dark';document.documentElement.classList.toggle('dark',t==='dark')}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="themeSwitcher" class="min-h-screen">
<header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl dark:border-white/10 dark:bg-[#050b14]/80">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-black tracking-tight">
            <span class="grid size-9 place-items-center rounded-xl bg-brand-400 text-slate-950">OTP</span><span>{{ $siteName }}</span>
        </a>
        <nav class="hidden items-center gap-2 md:flex">
            <a class="btn-secondary" href="{{ route('pricing') }}">Harga</a>
            @auth<a class="btn-primary" href="{{ route('dashboard') }}">Dashboard</a>@else<a class="btn-secondary" href="{{ route('login') }}">Login</a><a class="btn-primary" href="{{ route('register') }}">Daftar</a>@endauth
        </nav>
        <button type="button" @click="toggle()" class="btn-secondary !p-2.5" aria-label="Ganti tema">
            <svg x-show="theme==='dark'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 3v1.5M12 19.5V21M3 12h1.5M19.5 12H21M5.64 5.64l1.06 1.06m10.6 10.6 1.06 1.06m0-12.72-1.06 1.06M6.7 17.3l-1.06 1.06M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
            <svg x-show="theme==='light'" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z"/></svg>
        </button>
    </div>
</header>
<main class="pt-16">{{ $slot ?? '' }}@yield('content')</main>
<footer class="border-t border-slate-200 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-500">
    <div class="mx-auto max-w-7xl px-4">© {{ date('Y') }} {{ $siteName }} · <a class="hover:text-brand-400" href="{{ route('terms') }}">Syarat</a> · <a class="hover:text-brand-400" href="{{ route('privacy') }}">Privasi</a></div>
</footer>
</body>
</html>
