@extends('layouts.app')
@php($title = trim($service->name))
@section('content')
<div>
    <a href="{{ route('services.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-violet-600 dark:text-violet-300">← Kembali ke layanan</a>
    <div class="mt-5 flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
        <div class="flex items-center gap-4">
            <x-service-icon :service="$service" size="lg" />
            <h1 class="section-title">{{ trim($service->name) }}</h1>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:min-w-[400px]">
            <div class="card-soft p-3 text-center"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Termurah</div><div class="mt-1 text-sm font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) ($summary->lowest_price ?? 0), 0, ',', '.') }}</div></div>
            <div class="card-soft p-3 text-center"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Tertinggi</div><div class="mt-1 text-sm font-black">Rp {{ number_format((float) ($summary->highest_price ?? 0), 0, ',', '.') }}</div></div>
            <div class="card-soft p-3 text-center"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Stok</div><div class="mt-1 text-sm font-black text-emerald-600 dark:text-emerald-300">{{ number_format((int) ($summary->total_stock ?? 0)) }}</div></div>
        </div>
    </div>

    <form method="get" class="card mt-7 grid gap-4 p-5 sm:grid-cols-3" data-auto-filter>
        <div>
            <label class="label">Negara</label>
            <select class="input" name="country"><option value="">Semua negara</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected((string) request('country') === (string) $country->id)>{{ $country->name }}</option>@endforeach</select>
        </div>
        <div>
            <label class="label">Urutkan</label>
            <select class="input" name="sort"><option value="price_asc" @selected(request('sort', 'price_asc') === 'price_asc')>Harga termurah</option><option value="price_desc" @selected(request('sort') === 'price_desc')>Harga tertinggi</option><option value="stock" @selected(request('sort') === 'stock')>Stok terbanyak</option><option value="country" @selected(request('sort') === 'country')>Nama negara</option></select>
        </div>
        <div class="flex items-end">
            <input type="hidden" name="stock" value="1">
            <a href="{{ route('services.show', $service) }}" class="btn-secondary w-full">Atur ulang</a>
        </div>
    </form>

    <div class="mt-6 space-y-3 lg:hidden">
        @forelse($prices as $price)
            <article class="card p-5">
                <div class="flex items-start justify-between gap-3"><div><h2 class="font-black">{{ $price->country?->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $price->operator_name ?: 'Semua operator' }}</p></div><span class="badge {{ $price->stock > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' }}">{{ number_format($price->stock) }} stok</span></div>
                <div class="mt-5"><div class="flex items-end justify-between gap-4"><div><div class="text-xs font-bold uppercase tracking-wider text-slate-400">Harga</div><div class="mt-1 text-2xl font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $price->sell_price, 0, ',', '.') }}</div></div></div><div class="mt-4 grid grid-cols-2 gap-2"><form method="post" action="{{ route('orders.store') }}">@csrf<input type="hidden" name="price_id" value="{{ $price->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><input type="hidden" name="payment_channel" value="paykita"><button class="btn-primary w-full" @disabled($price->stock < 1)>Bayar PayKita</button></form><form method="post" action="{{ route('orders.store') }}">@csrf<input type="hidden" name="price_id" value="{{ $price->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><input type="hidden" name="payment_channel" value="balance"><button class="btn-secondary w-full" @disabled($price->stock < 1)>Pakai saldo</button></form></div></div>
            </article>
        @empty<div class="card p-10 text-center text-sm text-slate-500">Belum ada harga tersedia untuk filter ini.</div>@endforelse
    </div>

    <div class="table-wrap mt-6 hidden lg:block">
        <table class="table"><thead><tr><th>Negara</th><th>Operator</th><th>Harga</th><th>Stok</th><th></th></tr></thead><tbody>
        @forelse($prices as $price)
            <tr><td class="font-bold">{{ $price->country?->name }}</td><td>{{ $price->operator_name ?: 'Semua operator' }}</td><td class="font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) $price->sell_price, 0, ',', '.') }}</td><td>{{ number_format($price->stock) }}</td><td class="text-right"><div class="inline-flex gap-2"><form method="post" action="{{ route('orders.store') }}">@csrf<input type="hidden" name="price_id" value="{{ $price->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><input type="hidden" name="payment_channel" value="paykita"><button class="btn-primary !px-4 !py-2" @disabled($price->stock < 1)>Bayar PayKita</button></form><form method="post" action="{{ route('orders.store') }}">@csrf<input type="hidden" name="price_id" value="{{ $price->id }}"><input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}"><input type="hidden" name="payment_channel" value="balance"><button class="btn-secondary !px-4 !py-2" @disabled($price->stock < 1)>Pakai saldo</button></form></div></td></tr>
        @empty<tr><td colspan="5" class="py-12 text-center text-slate-500">Belum ada harga tersedia untuk filter ini.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="mt-8">{{ $prices->links() }}</div>
</div>
@endsection
