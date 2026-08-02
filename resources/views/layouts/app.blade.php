<!DOCTYPE html>
<html lang="id" class="dark" data-default-theme="{{ auth()->user()->theme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Dashboard' }} — {{ $siteName }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <script>try{const t=localStorage.getItem('theme')||document.documentElement.dataset.defaultTheme||'dark';document.documentElement.classList.toggle('dark',t==='dark')}catch(e){}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="themeSwitcher" class="min-h-screen">
<div x-data="{ sidebar:false }" class="min-h-screen">
    <div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 z-40 bg-slate-950/70 lg:hidden"></div>
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white p-4 transition-transform dark:border-white/10 dark:bg-[#07111f] lg:translate-x-0">
        <a href="{{ route('dashboard') }}" class="mb-6 flex items-center gap-3 px-2 font-black tracking-tight">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-400 text-sm text-slate-950">OTP</span><span>{{ $siteName }}</span>
        </a>
        <nav class="flex-1 space-y-1 overflow-y-auto pr-1">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}">⌂ <span>Dashboard</span></a>
            <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'nav-link-active' : '' }}">▦ <span>Layanan OTP</span></a>
            <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'nav-link-active' : '' }}">◫ <span>Pesanan</span></a>
            <a href="{{ route('topups.index') }}" class="nav-link {{ request()->routeIs('topups.*') ? 'nav-link-active' : '' }}">＋ <span>Top Up</span></a>
            <a href="{{ route('wallet.index') }}" class="nav-link {{ request()->routeIs('wallet.*') ? 'nav-link-active' : '' }}">↕ <span>Mutasi Saldo</span></a>
            <a href="{{ route('announcements.index') }}" class="nav-link {{ request()->routeIs('announcements.*') ? 'nav-link-active' : '' }}">◉ <span>Pengumuman</span></a>
            @if(auth()->user()->isAdmin())
                <div class="px-3 pb-1 pt-5 text-[11px] font-bold uppercase tracking-[.2em] text-slate-400">Administrator</div>
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'nav-link-active' : '' }}">◇ <span>Ringkasan Admin</span></a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">♙ <span>Pengguna & Saldo</span></a>
                <a href="{{ route('admin.orders.index') }}" class="nav-link {{ request()->routeIs('admin.orders.*') ? 'nav-link-active' : '' }}">▤ <span>Semua Pesanan</span></a>
                <a href="{{ route('admin.topups.index') }}" class="nav-link {{ request()->routeIs('admin.topups.*') ? 'nav-link-active' : '' }}">▣ <span>Pembayaran</span></a>
                <a href="{{ route('admin.announcements.index') }}" class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'nav-link-active' : '' }}">✦ <span>Kelola Pengumuman</span></a>
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'nav-link-active' : '' }}">▥ <span>Laporan</span></a>
                <a href="{{ route('admin.backups.index') }}" class="nav-link {{ request()->routeIs('admin.backups.*') ? 'nav-link-active' : '' }}">⇩ <span>Backup Database</span></a>
                <a href="{{ route('admin.settings.index') }}" class="nav-link {{ request()->routeIs('admin.settings.*') ? 'nav-link-active' : '' }}">⚙ <span>Pengaturan</span></a>
            @endif
        </nav>
        <div class="mt-4 rounded-2xl border border-slate-200 p-3 dark:border-white/10">
            <div class="truncate text-sm font-semibold">{{ auth()->user()->name }}</div>
            <div class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</div>
            <div class="mt-3 flex gap-2">
                <a href="{{ route('profile.edit') }}" class="btn-secondary flex-1 !px-2 !py-2">Profil</a>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="btn-secondary !px-3 !py-2" title="Logout">↪</button></form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-slate-50/85 backdrop-blur-xl dark:border-white/10 dark:bg-[#050b14]/85">
            <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3"><button @click="sidebar=true" class="btn-secondary !p-2 lg:hidden">☰</button><div><div class="text-xs text-slate-500">Saldo aktif</div><div class="font-bold text-brand-600 dark:text-brand-300">Rp {{ number_format((float) auth()->user()->fresh()->balance, 0, ',', '.') }}</div></div></div>
                <div class="flex items-center gap-2"><a href="{{ route('topups.index') }}" class="btn-primary hidden sm:inline-flex">Top Up</a><button type="button" @click="toggle()" class="btn-secondary !p-2.5" aria-label="Ganti tema"><span x-text="theme==='dark' ? '☀' : '☾'"></span></button></div>
            </div>
        </header>
        <main class="p-4 sm:p-6 lg:p-8">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
