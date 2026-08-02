@extends('layouts.guest')
@php($title = 'Harga layanan')
@section('content')
<section class="mx-auto min-h-[75vh] max-w-7xl px-4 py-16 sm:px-6">
    <div class="max-w-2xl"><span class="badge bg-brand-400/10 text-brand-700 dark:text-brand-300">Harga terbaru</span><h1 class="mt-5 text-4xl font-black tracking-tight">Harga layanan OTP</h1><p class="mt-4 text-slate-500">Harga jual sudah termasuk markup yang diatur administrator. Stok dan harga dapat berubah saat sinkronisasi provider.</p></div>
    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($prices as $price)
            <article class="card p-5"><div class="flex items-start justify-between gap-3"><div><h2 class="font-bold">{{ $price->service?->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $price->country?->name }}</p></div><span class="badge {{ $price->stock > 0 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300' : 'bg-slate-500/10 text-slate-500' }}">Stok {{ $price->stock }}</span></div><div class="mt-6 text-2xl font-black text-brand-600 dark:text-brand-300">Rp {{ number_format((float)$price->sell_price,0,',','.') }}</div><p class="mt-2 text-xs text-slate-500">{{ $price->operator_name ?: 'Semua operator' }}</p></article>
        @empty<div class="card col-span-full p-10 text-center text-slate-500">Katalog belum disinkronkan.</div>@endforelse
    </div>
    <div class="mt-8">{{ $prices->links() }}</div>
</section>
@endsection
