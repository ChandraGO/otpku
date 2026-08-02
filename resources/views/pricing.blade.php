@extends('layouts.guest')
@php($title = 'Harga layanan')
@section('content')
<section class="page-grid min-h-[75vh] py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="max-w-3xl"><span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Transparent Pricing</span><h1 class="text-balance mt-5 text-4xl font-black tracking-tight sm:text-5xl">Harga layanan OTP terbaru</h1><p class="mt-4 text-lg leading-8 text-slate-500 dark:text-slate-400">Tidak ada biaya tersembunyi. Harga yang tampil sudah dalam Rupiah dan telah mengikuti markup administrator.</p></div>
        <form method="get" class="card mt-9 flex flex-col gap-3 p-4 sm:flex-row"><label class="relative flex-1"><x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-cyan-500" /><input class="input !pl-12" name="q" value="{{ request('q') }}" placeholder="Cari layanan..."></label><select class="input sm:max-w-xs" name="sort"><option value="popular" @selected(request('sort', 'popular') === 'popular')>Rekomendasi</option><option value="price_asc" @selected(request('sort') === 'price_asc')>Harga termurah</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Harga tertinggi</option><option value="name" @selected(request('sort') === 'name')>Nama A–Z</option></select><button class="btn-primary">Cari</button></form>

        <div class="mt-8 space-y-3 lg:hidden">
            @forelse($services as $service)
                <article class="service-row"><x-service-icon :service="$service" /><div class="min-w-0 flex-1"><div class="truncate font-black">{{ trim($service->name) }}</div><div class="mt-1 text-xs text-slate-500">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }} – Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</div><div class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-300">Stock {{ number_format((int) $service->total_stock) }}</div></div>@auth<a href="{{ route('services.show', $service) }}" class="btn-primary !px-3 !py-2">Pilih</a>@else<a href="{{ route('login') }}" class="btn-primary !px-3 !py-2">Masuk</a>@endauth</article>
            @empty<div class="card p-10 text-center text-slate-500">Katalog belum disinkronkan.</div>@endforelse
        </div>

        <div class="table-wrap mt-8 hidden lg:block"><table class="table"><thead><tr><th>Services</th><th>Lowest price</th><th>Highest price</th><th>Stocks</th><th></th></tr></thead><tbody>@forelse($services as $service)<tr><td><div class="flex items-center gap-3"><x-service-icon :service="$service" size="sm" /><span class="font-bold">{{ trim($service->name) }}</span></div></td><td class="font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $service->lowest_price, 0, ',', '.') }}</td><td>Rp {{ number_format((float) $service->highest_price, 0, ',', '.') }}</td><td>{{ number_format((int) $service->total_stock) }} pcs</td><td class="text-right">@auth<a href="{{ route('services.show', $service) }}" class="btn-secondary !px-3 !py-2">Pilih</a>@else<a href="{{ route('login') }}" class="btn-secondary !px-3 !py-2">Login</a>@endauth</td></tr>@empty<tr><td colspan="5" class="py-12 text-center text-slate-500">Katalog belum disinkronkan.</td></tr>@endforelse</tbody></table></div>
        <div class="mt-8">{{ $services->links() }}</div>
    </div>
</section>
@endsection
