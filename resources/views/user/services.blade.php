@extends('layouts.app')
@php($title = 'List Services')
@section('content')
<div x-data="{ filters: {{ request()->hasAny(['country','sort','stock']) ? 'true' : 'false' }} }">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">SMS Virtual Catalog</span>
            <h1 class="section-title mt-4">List Services</h1>
            <p class="section-copy">Cari aplikasi, bandingkan rentang harga, lalu pilih negara dan nomor yang tersedia.</p>
        </div>
        <div class="hidden rounded-3xl border border-violet-200 bg-white px-5 py-3 text-right shadow-sm dark:border-violet-400/20 dark:bg-white/5 sm:block">
            <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Balance aktif</div>
            <div class="mt-1 text-lg font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) auth()->user()->balance, 0, ',', '.') }}</div>
        </div>
    </div>

    <form method="get" class="mt-7">
        <div class="flex gap-3">
            <label class="relative block flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-cyan-500" />
                <input class="input !rounded-3xl !py-4 !pl-12" name="q" value="{{ request('q') }}" placeholder="Search services..." autocomplete="off">
            </label>
            <button type="button" @click="filters = !filters" class="btn-secondary !rounded-3xl !px-4 sm:hidden" aria-label="Buka filter">
                <x-icon name="filter" />
            </button>
            <button class="btn-primary hidden !rounded-3xl px-6 sm:inline-flex">Cari</button>
        </div>

        <div x-show="filters" class="mt-4 sm:block">
            <div class="card grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="label">Negara</label>
                    <select class="input" name="country">
                        <option value="">Semua negara</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) request('country') === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Urutkan</label>
                    <select class="input" name="sort">
                        <option value="popular" @selected(request('sort', 'popular') === 'popular')>Rekomendasi</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Harga termurah</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Harga tertinggi</option>
                        <option value="stock" @selected(request('sort') === 'stock')>Stok terbanyak</option>
                        <option value="name" @selected(request('sort') === 'name')>Nama A–Z</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                        <input type="hidden" name="stock" value="0">
                        <input type="checkbox" name="stock" value="1" @checked(request('stock', '1') !== '0') class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        Hanya stok tersedia
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <button class="btn-primary flex-1">Terapkan filter</button>
                    <a href="{{ route('services.index') }}" class="btn-secondary">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="scrollbar-thin mt-5 flex gap-2 overflow-x-auto pb-2">
        @foreach([
            ['popular', 'Rekomendasi'],
            ['price_asc', 'Termurah'],
            ['stock', 'Stok banyak'],
            ['name', 'A–Z'],
        ] as [$value, $label])
            <a href="{{ route('services.index', array_filter(['q' => request('q'), 'country' => request('country'), 'stock' => request('stock', 1), 'sort' => $value])) }}" class="filter-chip {{ request('sort', 'popular') === $value ? 'filter-chip-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="mt-6 space-y-3 lg:hidden">
        @forelse($services as $service)
            <a href="{{ route('services.show', $service) }}" class="service-row">
                <x-service-icon :service="$service" />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-base font-black">{{ trim($service->name) }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                        <span>Lowest: <strong class="text-cyan-600 dark:text-cyan-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</strong></span>
                        <span>·</span>
                        <span>Highest: Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</span>
                    </div>
                    <div class="mt-1 text-xs font-semibold text-slate-400">Stock: {{ number_format((int) $service->total_stock) }} · {{ number_format((int) $service->available_variants) }} pilihan</div>
                </div>
                <x-icon name="chevron-right" class="text-slate-400" />
            </a>
        @empty
            <div class="card p-10 text-center text-sm text-slate-500">Tidak ada layanan yang sesuai filter.</div>
        @endforelse
    </div>

    <div class="table-wrap mt-6 hidden lg:block">
        <table class="table">
            <thead><tr><th>Services</th><th>Lowest price</th><th>Highest price</th><th>Stocks</th><th>Options</th><th></th></tr></thead>
            <tbody>
                @forelse($services as $service)
                    <tr class="transition hover:bg-violet-50/60 dark:hover:bg-white/[.025]">
                        <td><div class="flex items-center gap-3"><x-service-icon :service="$service" size="sm" /><span class="font-bold">{{ trim($service->name) }}</span></div></td>
                        <td class="font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</td>
                        <td><span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">{{ number_format((int) $service->total_stock) }} pcs</span></td>
                        <td>{{ number_format((int) $service->available_variants) }} negara/operator</td>
                        <td class="text-right"><a href="{{ route('services.show', $service) }}" class="btn-secondary !px-3 !py-2">Pilih <x-icon name="chevron-right" size="size-4" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-12 text-center text-slate-500">Tidak ada layanan yang sesuai filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-8">{{ $services->links() }}</div>
</div>
@endsection
