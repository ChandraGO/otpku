@extends('layouts.app')
@php($title = 'Detail Isi Saldo')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><a class="text-sm text-brand-600 dark:text-brand-300" href="{{ route('admin.topups.index') }}">← Pembayaran</a><h1 class="mt-2 text-3xl font-black">{{ $topup->order_id }}</h1><p class="mt-1 text-sm text-slate-500">{{ $topup->user?->email }}</p></div><x-status :value="$topup->status" /></div>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="font-bold">Rincian lokal</h2>
        <dl class="mt-5 space-y-4 text-sm">
            @foreach([
                'Penyedia pembayaran'=>$gatewayLabel,
                'Referensi penyedia'=>$topup->provider_reference,
                'Nominal'=>'Rp '.number_format((float)$topup->amount,0,',','.'),
                'Biaya'=>'Rp '.number_format((float)$topup->fee,0,',','.'),
                'Total bayar'=>'Rp '.number_format((float)$topup->total_payment,0,',','.'),
                'Metode'=>$topup->payment_method,
                'Nomor pembayaran'=>$topup->payment_number,
                'Dibayar'=>$topup->paid_at?->format('d M Y H:i'),
                'Dikreditkan'=>$topup->credited_at?->format('d M Y H:i'),
                'Kedaluwarsa'=>$topup->expires_at?->format('d M Y H:i')
            ] as $label=>$value)
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $label }}</dt><dd class="max-w-xs break-all text-right font-semibold">{{ $value ?: '—' }}</dd></div>
            @endforeach
        </dl>
        <form class="mt-6" method="post" action="{{ route('admin.topups.verify',$topup) }}">@csrf<button class="btn-primary w-full">Verifikasi ulang ke {{ $gatewayLabel }}</button></form>
    </section>
    <section class="card p-6"><h2 class="font-bold">Verifikasi penyedia</h2><div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">Respons mentah, API key, signature, dan tautan checkout penyedia tidak dirender ke antarmuka pengguna. Verifikasi dilakukan server-to-server berdasarkan gateway yang tersimpan pada invoice.</div></section>
</div>
@endsection
