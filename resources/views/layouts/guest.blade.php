<!DOCTYPE html>
<html lang="id" data-default-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('theme');
                const theme = ['dark', 'light'].includes(saved) ? saved : 'light';
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (_) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
<header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur-2xl dark:border-white/10 dark:bg-[#070b16]/85">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('home') }}"><x-app-logo /></a>
        <nav class="hidden items-center gap-2 md:flex">
            <a class="btn-secondary" href="{{ route('home') }}#features">Fitur</a>
            <a class="btn-secondary" href="{{ route('pricing') }}">Harga</a>
            @auth
                <a class="btn-primary" href="{{ route('dashboard') }}">Dashboard <x-icon name="arrow-right" size="size-4" /></a>
            @else
                <a class="btn-secondary" href="{{ route('login') }}">Login</a>
                <a class="btn-primary" href="{{ route('register') }}">Daftar</a>
            @endauth
        </nav>
        <div class="flex items-center gap-2">
            <button type="button" @click="$store.theme.toggle()" class="btn-secondary !p-2.5" aria-label="Ganti tema">
                <x-icon x-show="$store.theme.current === 'light'" name="moon" />
                <x-icon x-show="$store.theme.current === 'dark'" x-cloak name="sun" />
            </button>
            @guest<a class="btn-primary !px-3 md:hidden" href="{{ route('login') }}">Masuk</a>@endguest
            @auth<a class="btn-primary !px-3 md:hidden" href="{{ route('dashboard') }}">Dashboard</a>@endauth
        </div>
    </div>
</header>
<main class="pt-20">{{ $slot ?? '' }}@yield('content')</main>
<footer class="border-t border-slate-200 bg-white py-10 text-sm text-slate-500 dark:border-white/10 dark:bg-[#090f1d] dark:text-slate-400">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-4 text-center sm:flex-row sm:text-left">
        <x-app-logo />
        <div>© {{ date('Y') }} {{ $siteName }} · <a class="hover:text-violet-500" href="{{ route('terms') }}">Syarat</a> · <a class="hover:text-violet-500" href="{{ route('privacy') }}">Privasi</a></div>
    </div>
</footer>
</body>
</html>
