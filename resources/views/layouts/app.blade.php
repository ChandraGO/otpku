<!DOCTYPE html>
<html lang="id" class="{{ (auth()->user()->theme ?? 'dark') === 'dark' ? 'dark' : '' }}" data-default-theme="{{ auth()->user()->theme ?? 'dark' }}" data-theme-sync-url="{{ route('profile.theme') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Dasbor' }} — {{ $siteName }}</title>
    <link rel="icon" href="{{ filled($siteLogoUrl ?? null) ? $siteLogoUrl : '/favicon.svg' }}">
    @if(filled($siteLogoUrl ?? null))
        <link rel="preload" as="image" href="{{ $siteLogoUrl }}" fetchpriority="high">
    @endif
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('theme');
                const fallback = document.documentElement.dataset.defaultTheme || 'dark';
                const theme = ['dark', 'light'].includes(saved) ? saved : fallback;
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            } catch (_) {
                const fallback = document.documentElement.dataset.defaultTheme || 'dark';
                document.documentElement.classList.toggle('dark', fallback === 'dark');
                document.documentElement.dataset.theme = fallback;
                document.documentElement.style.colorScheme = fallback;
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
@include('partials.ui-runtime')
@php($simpleDashboardHeader = request()->routeIs('dashboard', 'admin.dashboard'))
<div class="min-h-screen" data-app-shell @if($simpleDashboardHeader) data-force-sidebar-expanded @endif>
    <div
        data-sidebar-overlay
        hidden
        class="fixed inset-0 z-40 bg-slate-950/55 backdrop-blur-sm lg:hidden"
    ></div>

    <aside
        id="app-sidebar"
        data-sidebar-panel
        data-open="false"
        class="fixed inset-y-0 left-0 z-50 flex w-[280px] -translate-x-full flex-col border-r border-slate-200/80 bg-white px-4 py-5 shadow-2xl transition-transform duration-300 dark:border-white/10 dark:bg-[#0a1020] lg:translate-x-0 lg:shadow-none"
    >
        <div class="flex min-h-[104px] items-center justify-between gap-2 px-2">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center overflow-hidden" aria-label="{{ $siteName }}">
                <x-app-logo variant="sidebar" />
            </a>
            <button type="button" data-sidebar-close class="btn-secondary !p-2 lg:hidden" aria-label="Tutup menu">×</button>
        </div>

        <nav class="scrollbar-thin mt-4 flex-1 overflow-y-auto pr-1">
            <div class="nav-group-label">Beranda</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                <x-icon name="home" /><span>Dasbor</span>
            </a>
            <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'nav-link-active' : '' }}">
                <x-icon name="services" /><span>Daftar Layanan</span>
            </a>

            <div class="nav-group-label">Transaksi</div>
            <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'nav-link-active' : '' }}">
                <x-icon name="orders" /><span>Pesanan & Riwayat</span>
            </a>
            @unless(auth()->user()->isAdmin())
                <a href="{{ route('wallet.index') }}" class="nav-link {{ request()->routeIs('wallet.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="wallet" /><span>Mutasi Saldo</span>
                </a>

                <div class="nav-group-label">Isi Saldo</div>
                <a href="{{ route('topups.index') }}" class="nav-link {{ request()->routeIs('topups.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="topup" /><span>Isi Saldo</span>
                </a>
            @endunless

            <div class="nav-group-label">API</div>
            <a href="{{ route('api.docs') }}" class="nav-link {{ request()->routeIs('api.docs') ? 'nav-link-active' : '' }}">
                <x-icon name="api" /><span>Dokumentasi API</span>
            </a>

            <div class="nav-group-label">Bantuan</div>
            <a href="{{ route('support.index') }}" class="nav-link {{ request()->routeIs('support.*') ? 'nav-link-active' : '' }}">
                <x-icon name="ticket" /><span>Tiket & Bantuan</span>
            </a>

            <div class="nav-group-label">Profil</div>
            <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'nav-link-active' : '' }}">
                <x-icon name="user" /><span>Pengaturan Akun</span>
            </a>
            <a href="{{ route('announcements.index') }}" class="nav-link {{ request()->routeIs('announcements.*') ? 'nav-link-active' : '' }}">
                <x-icon name="announcement" /><span>Pengumuman</span>
            </a>

            @if(auth()->user()->isAdmin())
                <div class="nav-group-label">Admin</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'nav-link-active' : '' }}">
                    <x-icon name="chart" /><span>Ringkasan Operasional</span>
                </a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="users" /><span>Pengguna & Saldo</span>
                </a>
                <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="orders" /><span>Semua Pesanan</span>
                </a>
                <a href="{{ route('admin.topups.index') }}" class="nav-link {{ request()->routeIs('admin.topups.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="topup" /><span>Pembayaran</span>
                </a>
                <a href="{{ route('admin.activities.index') }}" class="nav-link {{ request()->routeIs('admin.activities.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="history" /><span>Riwayat Aktivitas</span>
                </a>
                <a href="{{ route('admin.announcements.index') }}" class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="announcement" /><span>Kelola Pengumuman</span>
                </a>
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="chart" /><span>Laporan</span>
                </a>
                <a href="{{ route('admin.backups.index') }}" class="nav-link {{ request()->routeIs('admin.backups.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="database" /><span>Cadangan Basis Data</span>
                </a>
                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="settings" /><span>Pengaturan Sistem</span>
                </a>
            @endif
        </nav>

        <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/[.035]">
            <div class="flex items-center gap-3">
                @php($accountAvatarUrl = auth()->user()->emailAvatarUrl())
                @if(filled($accountAvatarUrl))
                    <span class="size-10 shrink-0 overflow-hidden rounded-2xl bg-transparent" data-user-avatar>
                        <img
                            src="{{ $accountAvatarUrl }}"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                            referrerpolicy="no-referrer"
                            onerror="this.closest('[data-user-avatar]')?.remove()"
                        >
                    </span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="post" class="mt-3">@csrf
                <button class="btn-secondary w-full"><x-icon name="logout" size="size-4" /> Keluar</button>
            </form>
        </div>
    </aside>

    <div data-app-content class="min-h-screen transition-[padding] duration-300 lg:pl-[280px]">
        <header class="sticky top-0 z-30 hidden border-b border-slate-200/80 bg-slate-50/85 backdrop-blur-2xl dark:border-white/10 dark:bg-[#070b16]/85 lg:block">
            <div class="flex h-20 items-center justify-between px-8">
                <div class="flex items-center gap-4">
                    @unless($simpleDashboardHeader)
                        <button type="button" data-sidebar-collapse-toggle class="btn-secondary !p-3" aria-label="Sembunyikan sidebar" title="Sembunyikan sidebar">
                            <x-icon name="menu" />
                        </button>
                    @endunless
                    <div>
                        <div class="text-lg font-black">{{ auth()->user()->name }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @unless($simpleDashboardHeader)
                        <div class="flex items-center gap-3 rounded-3xl border border-violet-200 bg-white px-4 py-2.5 shadow-sm dark:border-violet-400/20 dark:bg-white/5">
                            <span class="grid size-9 place-items-center rounded-full bg-amber-400 font-black text-white">Rp</span>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $headerBalanceLabel ?? 'Saldo' }}</div>
                                <div class="font-black">@if($headerBalanceAvailable ?? true) Rp {{ number_format((float) ($headerBalance ?? auth()->user()->balance), 0, ',', '.') }} @else — @endif</div>
                            </div>
                        </div>
                        <a href="{{ $headerTopupUrl ?? route('topups.index') }}" class="btn-primary" @if($headerTopupExternal ?? false) target="_blank" rel="noopener" @endif><x-icon name="topup" size="size-4" /> {{ $headerTopupLabel ?? 'Isi Saldo' }}</a>
                    @endunless
                    <button type="button" data-theme-toggle class="btn-secondary !p-3" aria-label="Aktifkan mode terang" aria-pressed="true" title="Aktifkan mode terang">
                        <x-icon data-theme-icon="light" name="moon" hidden />
                        <x-icon data-theme-icon="dark" name="sun" />
                    </button>
                </div>
            </div>
        </header>

        <header class="sticky top-0 z-40 border-b border-white/20 bg-gradient-to-r from-cyan-500 to-violet-500 text-white shadow-lg shadow-cyan-500/10 lg:hidden">
            <div class="flex min-h-20 items-center gap-3 px-4 py-3">
                <button
                    type="button"
                    data-sidebar-open
                    aria-controls="app-sidebar"
                    aria-expanded="false"
                    class="grid size-11 shrink-0 place-items-center rounded-2xl bg-white/15 text-white backdrop-blur"
                    style="border: 1px solid rgba(255,255,255,.35); background-color: rgba(15,23,42,.22); color: #fff;"
                    aria-label="Buka menu"
                    title="Buka menu"
                >
                    <span aria-hidden="true" style="display:flex;width:22px;flex-direction:column;gap:5px;">
                        <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                        <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                        <span style="display:block;height:2px;width:100%;border-radius:999px;background:currentColor;"></span>
                    </span>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-lg font-black">{{ auth()->user()->name }}</div>
                </div>
                <button type="button" data-theme-toggle class="grid size-11 shrink-0 place-items-center rounded-2xl border border-white/35 bg-slate-950/20 text-white" aria-label="Aktifkan mode terang" aria-pressed="true" title="Aktifkan mode terang">
                    <x-icon data-theme-icon="light" name="moon" hidden />
                    <x-icon data-theme-icon="dark" name="sun" />
                </button>
                @unless($simpleDashboardHeader)
                    <a href="{{ $headerTopupUrl ?? route('topups.index') }}" class="rounded-2xl border border-white/35 bg-slate-950/20 px-3 py-2 text-right" @if($headerTopupExternal ?? false) target="_blank" rel="noopener" @endif>
                        <div class="text-[9px] font-bold uppercase tracking-wider text-white/70">{{ $headerBalanceLabel ?? 'Saldo' }}</div>
                        <div class="text-sm font-black">@if($headerBalanceAvailable ?? true) Rp {{ number_format((float) ($headerBalance ?? auth()->user()->balance), 0, ',', '.') }} @else — @endif</div>
                    </a>
                @endunless
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] p-4 pb-28 sm:p-6 sm:pb-28 lg:p-8 lg:pb-10">
            <x-flash />
            @yield('content')
        </main>
    </div>

    <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 px-3 pb-1 lg:hidden" aria-label="Menu utama mobile">
        <div class="mx-auto flex max-w-md items-stretch gap-1 rounded-[1.4rem] border border-slate-200/80 bg-white/95 p-1.5 shadow-[0_-10px_35px_rgba(15,23,42,.12)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#0a1020]/95">
            <a href="{{ route('dashboard') }}" class="mobile-nav-link {{ request()->routeIs('dashboard') ? 'mobile-nav-link-active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="home" size="size-5" /></span><span>Beranda</span>
            </a>
            <a href="{{ route('orders.index') }}" class="mobile-nav-link {{ request()->routeIs('orders.*') ? 'mobile-nav-link-active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="orders" size="size-5" /></span><span>Pesanan</span>
            </a>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="mobile-nav-link {{ request()->routeIs('admin.*') ? 'mobile-nav-link-active' : '' }}">
                    <span class="mobile-nav-icon"><x-icon name="chart" size="size-5" /></span><span>Admin</span>
                </a>
            @else
                <a href="{{ route('topups.index') }}" class="mobile-nav-link {{ request()->routeIs('topups.*') ? 'mobile-nav-link-active' : '' }}">
                    <span class="mobile-nav-icon"><x-icon name="topup" size="size-5" /></span><span>Isi Saldo</span>
                </a>
            @endif
            <a href="{{ route('support.index') }}" class="mobile-nav-link {{ request()->routeIs('support.*') ? 'mobile-nav-link-active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="ticket" size="size-5" /></span><span>Bantuan</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="mobile-nav-link {{ request()->routeIs('profile.*') ? 'mobile-nav-link-active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="user" size="size-5" /></span><span>Profil</span>
            </a>
        </div>
    </nav>
</div>
@stack('scripts')
</body>
</html>
