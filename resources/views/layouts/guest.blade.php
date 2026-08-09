<!DOCTYPE html>
<html lang="id" class="dark" data-default-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $metaTitle = request()->routeIs('home') && filled($siteSeoTitle ?? null) ? $siteSeoTitle : ($title ?? $siteName);
        $metaDescription = $description ?? (filled($siteSeoDescription ?? null) ? $siteSeoDescription : $siteDescription);
        $metaImage = $siteSeoImageUrl ?? '';
        if (filled($metaImage) && !\Illuminate\Support\Str::startsWith($metaImage, ['http://', 'https://'])) {
            $metaImage = url($metaImage);
        }
        $metaKeywords = trim(implode(', ', array_filter([$siteSeoKeywords ?? '', $siteSeoHashtags ?? ''])));
    @endphp
    <title>{{ $metaTitle }}@unless(request()->routeIs('home')) — {{ $siteName }}@endunless</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if(filled($metaKeywords))<meta name="keywords" content="{{ $metaKeywords }}">@endif
    <meta name="robots" content="{{ $robots ?? 'index,follow' }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(filled($metaImage))
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:alt" content="{{ $siteName }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $metaImage }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <link rel="icon" href="{{ filled($siteLogoUrl ?? null) ? $siteLogoUrl : '/favicon.svg' }}">
    @if(filled($siteLogoUrl ?? null))
        <link rel="preload" as="image" href="{{ $siteLogoUrl }}" fetchpriority="high">
    @endif
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('theme');
                const theme = ['dark', 'light'].includes(saved) ? saved : 'dark';
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (_) {
                document.documentElement.classList.add('dark');
                document.documentElement.dataset.theme = 'dark';
                document.documentElement.style.colorScheme = 'dark';
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
@include('partials.ui-runtime')
<header class="fixed inset-x-0 top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur-2xl dark:border-white/10 dark:bg-[#070b16]/85">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
        <a href="{{ route('home') }}" aria-label="Beranda" class="-ml-4 min-w-0 flex-1 overflow-hidden sm:ml-0 sm:flex-none">
            <span class="block sm:hidden"><x-app-logo compact /></span>
            <span class="hidden sm:inline-flex"><x-app-logo /></span>
        </a>

        <nav class="hidden items-center gap-2 md:flex" aria-label="Navigasi utama">
            @auth
                <a class="btn-primary" href="{{ route('dashboard') }}">Dasbor <x-icon name="arrow-right" size="size-4" /></a>
            @endauth
        </nav>

        <div class="flex items-center gap-2">
            <button
                type="button"
                data-theme-toggle
                class="btn-secondary !p-2.5"
                aria-label="Aktifkan mode terang"
                aria-pressed="true"
                title="Aktifkan mode terang"
            >
                <x-icon data-theme-icon="light" name="moon" hidden />
                <x-icon data-theme-icon="dark" name="sun" />
            </button>
            <button
                type="button"
                data-mobile-menu-toggle
                aria-expanded="false"
                aria-controls="mobile-menu"
                class="btn-secondary !p-2.5 md:hidden"
                style="min-width: 44px; min-height: 44px;"
                aria-label="Buka menu"
                title="Buka menu"
            >
                <span aria-hidden="true" style="display:flex;width:20px;flex-direction:column;gap:5px;">
                    <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                    <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                    <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                </span>
            </button>
        </div>
    </div>

    <div
        id="mobile-menu"
        data-mobile-menu
        hidden
        class="border-t border-slate-200/70 bg-white/95 px-4 py-4 shadow-xl backdrop-blur-2xl dark:border-white/10 dark:bg-[#0a1020]/95 md:hidden"
    >
        <nav class="mx-auto grid max-w-7xl gap-2" aria-label="Navigasi mobile">
            <a data-mobile-menu-close class="nav-link {{ request()->routeIs('home') ? 'nav-link-active' : '' }}" href="{{ route('home') }}"><x-icon name="home" /><span>Beranda</span></a>
            <a data-mobile-menu-close class="nav-link {{ request()->routeIs('pricing') ? 'nav-link-active' : '' }}" href="{{ route('pricing') }}"><x-icon name="chart" /><span>Harga</span></a>
            @auth
                <a data-mobile-menu-close class="nav-link nav-link-active" href="{{ route('dashboard') }}"><x-icon name="home" /><span>Dasbor</span></a>
            @else
                <a data-mobile-menu-close class="nav-link" href="{{ route('login') }}"><x-icon name="user" /><span>Masuk</span></a>
                <a data-mobile-menu-close class="btn-primary mt-1" href="{{ route('register') }}">Daftar</a>
            @endauth
        </nav>
    </div>
</header>
<main class="pt-20">{{ $slot ?? '' }}@yield('content')</main>
<footer class="border-t border-slate-200 bg-white py-10 text-sm text-slate-500 dark:border-white/10 dark:bg-[#090f1d] dark:text-slate-400">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-5 px-4 text-center sm:flex-row sm:text-left">
        <x-app-logo />
        <div>© {{ date('Y') }} {{ $siteName }} · <a class="hover:text-violet-500" href="{{ route('terms') }}">Syarat</a> · <a class="hover:text-violet-500" href="{{ route('privacy') }}">Privasi</a></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
