@extends('layouts.guest')
@php($title = 'Harga layanan')
@section('content')
<section class="page-grid min-h-[75vh] py-16">
    <div
        class="mx-auto max-w-7xl px-4 sm:px-6"
        x-data="liveServiceSearch({ endpoint: @js(route('pricing')), initialQuery: @js(request('q', '')), initialSort: @js(request('sort', 'popular')) })"
        @search-error.window="console.warn('Pencarian layanan gagal dimuat')"
    >
        <div class="max-w-3xl">
            <span class="badge bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Transparent Pricing</span>
            <h1 class="text-balance mt-5 text-4xl font-black tracking-tight sm:text-5xl">Harga layanan OTP terbaru</h1>
            <p class="mt-4 text-lg leading-8 text-slate-500 dark:text-slate-400">Bandingkan harga dan ketersediaan layanan dalam Rupiah sebelum melakukan pemesanan.</p>
        </div>

        <form @submit.prevent="search()" class="card mt-9 flex flex-col gap-3 p-4 sm:flex-row">
            <label class="relative flex-1">
                <span class="sr-only">Cari layanan</span>
                <x-icon name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-cyan-500" />
                <input
                    class="input !pl-12 !pr-11"
                    name="q"
                    x-model="query"
                    autocomplete="off"
                    placeholder="Cari layanan..."
                    aria-label="Cari layanan"
                >
                <span x-show="loading" x-cloak class="absolute right-4 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-violet-500/25 border-t-violet-500" aria-label="Memuat"></span>
            </label>
            <select class="input sm:max-w-xs" name="sort" x-model="sort" aria-label="Urutkan layanan">
                <option value="popular">Rekomendasi</option>
                <option value="price_asc">Harga termurah</option>
                <option value="price_desc">Harga tertinggi</option>
                <option value="name">Nama A–Z</option>
            </select>
            <noscript><button class="btn-primary">Cari</button></noscript>
        </form>

        <p class="mt-3 text-xs text-slate-500">Hasil otomatis diperbarui saat Anda mengetik.</p>

        <div x-ref="results" @click="follow($event)" :class="loading ? 'opacity-60' : 'opacity-100'" class="transition-opacity">
            @include('partials.pricing-results', ['services' => $services])
        </div>
    </div>
</section>
@endsection
