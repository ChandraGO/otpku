<!DOCTYPE html>
<html lang="id" class="{{ (auth()->user()->theme ?? 'dark') === 'dark' ? 'dark' : '' }}" data-default-theme="{{ auth()->user()->theme ?? 'dark' }}" data-theme-sync-url="{{ route('profile.theme') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Dashboard' }} — {{ $siteName }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
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
<div class="min-h-screen">
    <div
        data-sidebar-overlay
        hidden
        class="fixed inset-0 z-40 bg-slate-950/55 backdrop-blur-sm lg:hidden"
    ></div>

    <aside
        data-sidebar-panel
        data-open="false"
        class="fixed inset-y-0 left-0 z-50 flex w-[280px] -translate-x-full flex-col border-r border-slate-200/80 bg-white px-4 py-5 shadow-2xl transition-transform duration-300 dark:border-white/10 dark:bg-[#0a1020] lg:translate-x-0 lg:shadow-none"
    >
        <div class="flex items-center justify-between px-2">
            <a href="{{ route('dashboard') }}"><x-app-logo /></a>
            <button type="button" data-sidebar-close class="btn-secondary !p-2 lg:hidden" aria-label="Tutup menu">×</button>
        </div>

        <nav class="scrollbar-thin mt-7 flex-1 overflow-y-auto pr-1">
            <div class="nav-group-label">Home</div>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">
                <x-icon name="home" /><span>Dashboard</span>
            </a>
            <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'nav-link-active' : '' }}">
                <x-icon name="services" /><span>List Services</span>
            </a>

            <div class="nav-group-label">Transaction</div>
            <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'nav-link-active' : '' }}">
                <x-icon name="orders" /><span>Order & History</span>
            </a>
            <a href="{{ route('wallet.index') }}" class="nav-link {{ request()->routeIs('wallet.*') ? 'nav-link-active' : '' }}">
                <x-icon name="wallet" /><span>Balance Mutation</span>
            </a>

            <div class="nav-group-label">Deposit</div>
            <a href="{{ route('topups.index') }}" class="nav-link {{ request()->routeIs('topups.*') ? 'nav-link-active' : '' }}">
                <x-icon name="topup" /><span>Top Up Deposit</span>
            </a>

            <div class="nav-group-label">API</div>
            <a href="{{ route('api.docs') }}" class="nav-link {{ request()->routeIs('api.docs') ? 'nav-link-active' : '' }}">
                <x-icon name="api" /><span>API Docs</span>
            </a>

            <div class="nav-group-label">Tickets</div>
            <a href="{{ route('support.index') }}" class="nav-link {{ request()->routeIs('support.*') ? 'nav-link-active' : '' }}">
                <x-icon name="ticket" /><span>Tickets & Support</span>
            </a>

            <div class="nav-group-label">Profile</div>
            <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'nav-link-active' : '' }}">
                <x-icon name="user" /><span>Account Settings</span>
            </a>
            <a href="{{ route('announcements.index') }}" class="nav-link {{ request()->routeIs('announcements.*') ? 'nav-link-active' : '' }}">
                <x-icon name="announcement" /><span>Announcements</span>
            </a>

            @if(auth()->user()->isAdmin())
                <div class="nav-group-label">Administrator</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'nav-link-active' : '' }}">
                    <x-icon name="chart" /><span>Operational Summary</span>
                </a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="users" /><span>Users & Balance</span>
                </a>
                <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="orders" /><span>All Orders</span>
                </a>
                <a href="{{ route('admin.topups.index') }}" class="nav-link {{ request()->routeIs('admin.topups.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="topup" /><span>Payments</span>
                </a>
                <a href="{{ route('admin.announcements.index') }}" class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="announcement" /><span>Manage Announcements</span>
                </a>
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="chart" /><span>Reports</span>
                </a>
                <a href="{{ route('admin.backups.index') }}" class="nav-link {{ request()->routeIs('admin.backups.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="database" /><span>Database Backup</span>
                </a>
                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'nav-link-active' : '' }}">
                    <x-icon name="settings" /><span>System Settings</span>
                </a>
            @endif
        </nav>

        <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/[.035]">
            <div class="flex items-center gap-3">
                <div class="grid size-10 place-items-center rounded-2xl bg-violet-100 font-black text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="post" class="mt-3">@csrf
                <button class="btn-secondary w-full"><x-icon name="logout" size="size-4" /> Logout</button>
            </form>
        </div>
    </aside>

    <div class="min-h-screen lg:pl-[280px]">
        <header class="sticky top-0 z-30 hidden border-b border-slate-200/80 bg-slate-50/85 backdrop-blur-2xl dark:border-white/10 dark:bg-[#070b16]/85 lg:block">
            <div class="flex h-20 items-center justify-between px-8">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[.18em] text-slate-400">Hello, welcome back 👋</div>
                    <div class="mt-1 text-lg font-black">{{ auth()->user()->name }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-3 rounded-3xl border border-violet-200 bg-white px-4 py-2.5 shadow-sm dark:border-violet-400/20 dark:bg-white/5">
                        <span class="grid size-9 place-items-center rounded-full bg-amber-400 font-black text-white">Rp</span>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $headerBalanceLabel ?? 'Balance' }}</div>
                            <div class="font-black">@if($headerBalanceAvailable ?? true) Rp {{ number_format((float) ($headerBalance ?? auth()->user()->balance), 0, ',', '.') }} @else — @endif</div>
                        </div>
                    </div>
                    <a href="{{ $headerTopupUrl ?? route('topups.index') }}" class="btn-primary" @if($headerTopupExternal ?? false) target="_blank" rel="noopener" @endif><x-icon name="topup" size="size-4" /> {{ $headerTopupLabel ?? 'Top Up' }}</a>
                    <button type="button" data-theme-toggle class="btn-secondary !p-3" aria-label="Aktifkan mode terang" aria-pressed="true" title="Aktifkan mode terang">
                        <x-icon data-theme-icon="light" name="moon" hidden />
                        <x-icon data-theme-icon="dark" name="sun" />
                    </button>
                </div>
            </div>
        </header>

        <header class="sticky top-0 z-30 border-b border-white/20 bg-gradient-to-r from-cyan-500 to-violet-500 text-white shadow-lg shadow-cyan-500/10 lg:hidden">
            <div class="flex min-h-20 items-center gap-3 px-4 py-3">
                <button type="button" data-sidebar-open class="grid size-11 shrink-0 place-items-center rounded-2xl bg-white/15 backdrop-blur" aria-label="Buka menu">
                    <x-icon name="menu" size="size-6" />
                </button>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-medium text-white/75">Hello, welcome back 👋</div>
                    <div class="truncate text-lg font-black">{{ auth()->user()->name }}</div>
                </div>
                <a href="{{ $headerTopupUrl ?? route('topups.index') }}" class="rounded-2xl border border-white/35 bg-white/12 px-3 py-2 text-right backdrop-blur" @if($headerTopupExternal ?? false) target="_blank" rel="noopener" @endif>
                    <div class="text-[9px] font-bold uppercase tracking-wider text-white/70">{{ $headerBalanceLabel ?? 'Balance' }}</div>
                    <div class="text-sm font-black">@if($headerBalanceAvailable ?? true) Rp {{ number_format((float) ($headerBalance ?? auth()->user()->balance), 0, ',', '.') }} @else — @endif</div>
                </a>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] p-4 pb-28 sm:p-6 sm:pb-28 lg:p-8 lg:pb-10">
            <x-flash />
            @yield('content')
        </main>
    </div>

    <nav class="safe-bottom fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 px-2 pt-2 shadow-[0_-12px_35px_rgba(15,23,42,.08)] backdrop-blur-xl dark:border-white/10 dark:bg-[#0a1020]/95 lg:hidden">
        <div class="mx-auto flex max-w-xl gap-1">
            <a href="{{ route('dashboard') }}" class="mobile-nav-link {{ request()->routeIs('dashboard') ? 'mobile-nav-link-active' : '' }}">
                <x-icon name="home" size="size-5" /><span>Home</span>
            </a>
            <a href="{{ route('orders.index') }}" class="mobile-nav-link {{ request()->routeIs('orders.*') ? 'mobile-nav-link-active' : '' }}">
                <x-icon name="orders" size="size-5" /><span>Order</span>
            </a>
            <a href="{{ route('topups.index') }}" class="mobile-nav-link {{ request()->routeIs('topups.*') ? 'mobile-nav-link-active' : '' }}">
                <x-icon name="topup" size="size-5" /><span>Top Up</span>
            </a>
            <a href="{{ route('support.index') }}" class="mobile-nav-link {{ request()->routeIs('support.*') ? 'mobile-nav-link-active' : '' }}">
                <x-icon name="ticket" size="size-5" /><span>Ticket</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="mobile-nav-link {{ request()->routeIs('profile.*') ? 'mobile-nav-link-active' : '' }}">
                <x-icon name="user" size="size-5" /><span>Profile</span>
            </a>
        </div>
    </nav>
</div>
@include('partials.ui-runtime')
@stack('scripts')
</body>
</html>
