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
        // Jika URL lokal tersimpan saat domain lama masih aktif, gunakan host aplikasi
        // saat ini agar crawler sosial tidak perlu melewati redirect lintas domain.
        $metaImagePath = filled($metaImage) ? parse_url($metaImage, PHP_URL_PATH) : null;
        $isLocalSeoImage = in_array($metaImagePath, ['/meta/seo-image', '/og-image.jpg'], true);
        if ($isLocalSeoImage) {
            $query = parse_url($metaImage, PHP_URL_QUERY);
            $metaImage = route('meta.social-image').(filled($query) ? '?'.$query : '');
        }
        $metaImageWidth = max(0, (int) ($siteSeoImageWidth ?? 0));
        $metaImageHeight = max(0, (int) ($siteSeoImageHeight ?? 0));
        $metaImageMime = trim((string) ($siteSeoImageMime ?? ''));
        $metaImageVersion = null;
        if ($isLocalSeoImage) {
            // Endpoint /og-image.jpg selalu menormalisasi gambar menjadi JPEG 1200x630.
            // Iklankan metadata yang sama persis agar crawler sosial tidak menolak
            // gambar karena MIME/dimensi di HTML berbeda dengan response image.
            $metaImageWidth = 1200;
            $metaImageHeight = 630;
            $metaImageMime = 'image/jpeg';

            $metaImageQuery = parse_url($metaImage, PHP_URL_QUERY);
            if (filled($metaImageQuery)) {
                parse_str($metaImageQuery, $metaImageQueryParams);
                $metaImageVersion = $metaImageQueryParams['v'] ?? null;
            }
        }

        // Canonical tetap URL bersih. Untuk Open Graph homepage, gunakan versi
        // objek yang mengikuti versi gambar. Pengunjung tetap membagikan URL utama
        // tanpa query, tetapi scraper mendapat object URL baru saat thumbnail berubah.
        $metaOgUrl = request()->fullUrl();
        if (request()->routeIs('home') && filled($metaImageVersion) && !request()->has('v')) {
            $metaOgUrl = url('/').'?v='.rawurlencode((string) $metaImageVersion);
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
    <meta property="og:url" content="{{ $metaOgUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="id_ID">
    @if(filled($metaImage))
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:url" content="{{ $metaImage }}">
        @if(\Illuminate\Support\Str::startsWith($metaImage, 'https://'))
            <meta property="og:image:secure_url" content="{{ $metaImage }}">
        @endif
        @if(filled($metaImageMime))<meta property="og:image:type" content="{{ $metaImageMime }}">@endif
        @if($metaImageWidth > 0)<meta property="og:image:width" content="{{ $metaImageWidth }}">@endif
        @if($metaImageHeight > 0)<meta property="og:image:height" content="{{ $metaImageHeight }}">@endif
        <meta property="og:image:alt" content="Thumbnail {{ $siteName }}">
        <meta itemprop="image" content="{{ $metaImage }}">
        <link rel="image_src" href="{{ $metaImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $metaImage }}">
        <meta name="twitter:image:src" content="{{ $metaImage }}">
        <meta name="twitter:image:alt" content="Thumbnail {{ $siteName }}">
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
        <a href="{{ route('home') }}" aria-label="Beranda" class="min-w-0 flex-1 overflow-hidden sm:flex-none">
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
            <a data-mobile-menu-close class="nav-link {{ request()->routeIs('ratings.*') ? 'nav-link-active' : '' }}" href="{{ route('ratings.index') }}"><x-icon name="users" /><span>Rating</span></a>
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
        <div>© {{ date('Y') }} {{ $siteName }} · <a class="hover:text-violet-500" href="{{ route('ratings.index') }}">Rating</a> · <a class="hover:text-violet-500" href="{{ route('terms') }}">Syarat</a> · <a class="hover:text-violet-500" href="{{ route('privacy') }}">Privasi</a></div>
    </div>
</footer>
@stack('scripts')
</body>
</html>
