@extends('layouts.app')
@php($title = 'Isi Saldo')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <h1 class="section-title">Isi saldo Rupiah</h1>
        <div class="mt-2 inline-flex items-center gap-2 rounded-full bg-violet-500/10 px-3 py-1 text-xs font-black text-violet-700 dark:text-violet-300">
            Penyedia pembayaran: {{ $activeGatewayLabel }}
        </div>
    </div>
    <div class="card-soft px-5 py-3"><div class="text-[10px] font-black uppercase tracking-wider text-slate-400">Saldo sekarang</div><div class="mt-1 text-xl font-black text-violet-700 dark:text-violet-300">Rp {{ number_format((float) auth()->user()->balance, 0, ',', '.') }}</div></div>
</div>
<div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
<section class="card p-6">
    <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="topup" /></span><h2 class="text-lg font-black">Buat invoice</h2></div>
    <form class="mt-6 space-y-4" method="post" action="{{ route('topups.store') }}">@csrf
        <div><label class="label">Nominal Rupiah</label><input class="input" name="amount" type="number" min="{{ $minimum }}" max="{{ $maximum }}" step="1000" value="{{ old('amount',$minimum) }}" required><p class="mt-1 text-xs text-slate-500">Min Rp {{ number_format($minimum,0,',','.') }} · Maks Rp {{ number_format($maximum,0,',','.') }}</p></div>
        <div class="grid grid-cols-2 gap-2">@foreach(array_unique([$minimum,50000,100000,250000]) as $quick)@if($quick <= $maximum)<button type="button" data-quick-amount="{{ $quick }}" class="btn-secondary !py-2">Rp {{ number_format($quick,0,',','.') }}</button>@endif @endforeach</div>
        <div><label class="label">Metode pembayaran</label><select class="input" name="payment_method">@foreach($paymentMethods as $value=>$label)<option value="{{ $value }}" @selected(old('payment_method',$defaultMethod)===$value)>{{ $label }}</option>@endforeach</select></div>
        <button class="btn-primary w-full">Buat pembayaran</button>
    </form>
</section>
<section class="card p-5"><h2 class="text-lg font-black">Riwayat isi saldo</h2><div class="mt-4 table-wrap"><table class="table"><thead><tr><th>ID Pesanan</th><th>Nominal</th><th>Penyedia</th><th>Metode</th><th>Status</th><th></th></tr></thead><tbody>@forelse($topups as $topup)<tr><td class="font-mono text-xs">{{ $topup->order_id }}</td><td class="font-bold">Rp {{ number_format((float)$topup->amount,0,',','.') }}</td><td>{{ ucfirst($topup->gateway ?: 'paykita') }}</td><td>{{ strtoupper(str_replace('_',' ',$topup->payment_method)) }}</td><td><x-status :value="$topup->status" /></td><td><a class="btn-secondary !px-3 !py-2" href="{{ route('topups.show',$topup) }}">Detail</a></td></tr>@empty<tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada isi saldo.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $topups->links() }}</div></section>
</div>
@endsection
