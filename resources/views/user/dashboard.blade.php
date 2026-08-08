@extends('layouts.app')
@php($title = 'Dasbor')
@section('content')
@if($loginAnnouncement)
<div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm">
    <div class="card max-h-[90vh] w-full max-w-2xl overflow-y-auto" @click.outside="open = false">
        @if($loginAnnouncement->imageUrl())
            <img src="{{ $loginAnnouncement->imageUrl() }}" alt="Gambar {{ $loginAnnouncement->title }}" class="aspect-video w-full rounded-t-3xl object-cover">
        @endif
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-300">Pengumuman</div>
                    <h2 class="mt-2 text-2xl font-black">{{ $loginAnnouncement->title }}</h2>
                </div>
                <button type="button" class="btn-secondary !p-2" @click="open = false" aria-label="Tutup pengumuman">×</button>
            </div>
            <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $loginAnnouncement->body }}</p>
            <button type="button" class="btn-primary mt-6 w-full" @click="open = false">Mengerti</button>
        </div>
    </div>
</div>
@endif

<section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-violet-700 via-violet-600 to-cyan-500 p-6 text-white shadow-2xl shadow-violet-500/20 sm:p-8">
    <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/10 blur-3xl"></div>
    <div class="absolute -bottom-28 left-1/3 size-64 rounded-full bg-cyan-200/15 blur-3xl"></div>
    <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-center">
        <div class="max-w-2xl">
            <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Halo, {{ $user->name }} 👋</h1>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('services.index') }}" class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-black text-violet-700 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">Beli OTP <x-icon name="arrow-right" size="size-4" /></a>
                @if($user->isAdmin())
                    <a href="https://sms-virtual.net" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-2xl border border-white/30 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20"><x-icon name="topup" size="size-4" /> Isi Saldo Penyedia</a>
                @else
                    <a href="{{ route('topups.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/30 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20"><x-icon name="topup" size="size-4" /> Isi Saldo</a>
                @endif
            </div>
        </div>
        <div class="min-w-[240px] rounded-3xl border border-white/20 bg-white/10 p-5 backdrop-blur-xl">
            <div class="text-xs font-bold uppercase tracking-[.18em] text-white/65">{{ $dashboardBalanceLabel }}</div>
            <div class="mt-2 text-3xl font-black">
                @if($dashboardBalanceAvailable)
                    Rp {{ number_format((float) $dashboardBalance, 0, ',', '.') }}
                @else
                    —
                @endif
            </div>
            @if($user->isAdmin())
                <div class="mt-4 flex items-start gap-2 text-xs text-white/75">
                    <span class="mt-1 size-2 shrink-0 rounded-full {{ $dashboardBalanceSource === 'provider' ? 'bg-emerald-300' : 'bg-amber-300' }}"></span>
                    <span>{{ $dashboardBalanceSource === 'provider' ? 'Saldo langsung dari penyedia.' : ($dashboardBalanceError ?: 'Saldo penyedia terakhir.') }}</span>
                </div>
            @else
                <div class="mt-4 flex items-center gap-2 text-xs text-white/75"><span class="size-2 rounded-full bg-emerald-300"></span> Akun aktif dan siap bertransaksi</div>
            @endif
        </div>
    </div>
</section>

<section class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
    @foreach([
        ['Pesanan aktif', $activeOrders->count(), 'orders', 'from-violet-500 to-violet-600'],
        $user->isAdmin()
            ? ['Saldo operasional', 'Penyedia', 'topup', 'from-cyan-500 to-cyan-600']
            : ['Isi saldo tertunda', $pendingTopups->count(), 'topup', 'from-cyan-500 to-cyan-600'],
        ['Status akun', ['active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'banned' => 'Diblokir'][$user->status] ?? $user->status, 'shield', 'from-emerald-500 to-emerald-600'],
        ['API pelanggan', filled($user->api_key_hash) ? 'Siap' : 'Belum', 'api', 'from-amber-500 to-orange-500'],
    ] as $stat)
        <article class="group stat-card relative overflow-hidden transition duration-300 hover:-translate-y-1 hover:shadow-xl">
            <div class="absolute -right-8 -top-8 size-24 rounded-full bg-gradient-to-br {{ $stat[3] }} opacity-10 transition group-hover:scale-125"></div>
            <span class="grid size-10 place-items-center rounded-2xl bg-gradient-to-br {{ $stat[3] }} text-white shadow-lg"><x-icon :name="$stat[2]" size="size-5" /></span>
            <div class="mt-4 text-2xl font-black">{{ $stat[1] }}</div>
            <div class="mt-1 text-xs font-black uppercase tracking-wider text-slate-400">{{ $stat[0] }}</div>
        </article>
    @endforeach
</section>

<section class="mt-8">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <h2 class="text-2xl font-black">Layanan rekomendasi</h2>
        <a href="{{ route('services.index') }}" class="btn-secondary">Lihat semua <x-icon name="arrow-right" size="size-4" /></a>
    </div>
    <div class="mt-5 grid gap-3 md:grid-cols-2 2xl:grid-cols-4">
        @forelse($featuredServices as $service)
            <a href="{{ route('services.show', $service) }}" class="service-row group">
                <x-service-icon :service="$service" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-black group-hover:text-violet-600 dark:group-hover:text-violet-300">{{ trim($service->name) }}</div>
                    <div class="mt-1 text-xs text-slate-500">Mulai <span class="font-bold text-cyan-600 dark:text-cyan-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</span></div>
                    <div class="mt-1 text-xs text-slate-400">Stok {{ number_format((int) $service->total_stock) }}</div>
                </div>
                <x-icon name="chevron-right" class="text-slate-400 transition group-hover:translate-x-1" />
            </a>
        @empty
            <div class="card col-span-full p-10 text-center text-sm text-slate-500">Katalog belum tersedia.</div>
        @endforelse
    </div>
</section>

<section class="card mt-8 p-5 sm:p-6">
    <div class="flex items-center justify-between"><h2 class="text-lg font-black">Pesanan aktif</h2><a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('orders.index') }}">Semua</a></div>
    <div class="mt-5 space-y-3">
        @forelse($activeOrders as $order)
            <a href="{{ route('orders.show', $order) }}" class="flex flex-col justify-between gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-violet-300 hover:bg-violet-50/50 dark:border-white/10 dark:hover:border-violet-400/30 dark:hover:bg-white/[.03] sm:flex-row sm:items-center">
                <div><div class="font-bold">{{ $order->service_name }} · {{ $order->country_name }}</div><div class="mt-1 text-xs text-slate-500">{{ $order->phone_number ?: 'Nomor sedang disiapkan' }}</div></div>
                <x-status :value="$order->status" />
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-white/10">Belum ada pesanan aktif.</div>
        @endforelse
    </div>
</section>

@unless($user->isAdmin())
<section class="card mt-6 p-5 sm:p-6">
    <div class="flex items-center justify-between"><h2 class="text-lg font-black">Mutasi terbaru</h2><a href="{{ route('wallet.index') }}" class="text-sm font-bold text-violet-600 dark:text-violet-300">Lihat mutasi</a></div>
    <div class="mt-5 table-wrap">
        <table class="table"><thead><tr><th>Waktu</th><th>Keterangan</th><th>Arah</th><th>Nominal</th><th>Saldo</th></tr></thead><tbody>
            @forelse($recentTransactions as $tx)
                <tr><td>{{ $tx->created_at->format('d M Y H:i') }}</td><td>{{ $tx->description }}</td><td><x-status :value="$tx->direction" /></td><td class="font-bold {{ $tx->direction === 'credit' ? 'text-emerald-500' : 'text-rose-500' }}">{{ $tx->direction === 'credit' ? '+' : '-' }} Rp {{ number_format((float) $tx->amount, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $tx->balance_after, 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-slate-500">Belum ada mutasi.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</section>
@endunless
@endsection
