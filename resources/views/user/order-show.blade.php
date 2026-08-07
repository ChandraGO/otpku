@extends('layouts.app')
@php
    $title = 'Detail Pesanan';
    $hasActivation = filled($order->provider_activation_id);
    $terminal = $order->isTerminal();
    $hasOtp = $order->hasOtp();
    $canCancelLocally = ! $hasActivation && in_array($order->status, ['processing', 'provider_pending'], true);
    $initialPayload = [
        'status' => $order->status,
        'phone_number' => $order->phone_number,
        'otp_code' => $order->otp_code,
        'message' => $order->provider_message,
        'expires_at' => $order->expires_at?->toIso8601String(),
        'provider_activation_id' => $order->provider_activation_id,
        'terminal' => $terminal,
        'can' => [
            'ready' => $hasActivation && ! $terminal,
            'resend' => $hasActivation && ! $terminal,
            'complete' => $hasActivation && ! $terminal,
            'cancel' => $canCancelLocally || ($hasActivation && ! $terminal && ! $hasOtp),
            'reactivate' => $hasActivation && in_array($order->status, ['cancelled', 'expired', 'failed'], true),
        ],
    ];
    $initialPayloadEncoded = base64_encode(json_encode($initialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
@endphp

@section('content')
<div
    x-data="orderStatus"
    data-status-url="{{ route('orders.status', $order) }}"
    data-initial="{{ $initialPayloadEncoded }}"
>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a class="text-sm text-brand-600 dark:text-brand-300" href="{{ route('orders.index') }}">← Kembali</a>
            <h1 class="mt-2 text-3xl font-black tracking-tight">{{ $order->service_name }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $order->country_name }} · {{ $order->operator_name ?: 'Semua operator' }}</p>
        </div>
        <span class="badge bg-sky-500/10 capitalize text-sky-600 dark:text-sky-300" x-text="statusLabel">
            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
        </span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-wider text-slate-500">Nomor</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="break-all text-2xl font-black" x-text="data.phone_number || 'Sedang disiapkan...'">{{ $order->phone_number ?: 'Sedang disiapkan...' }}</span>
                        <button type="button" x-show="data.phone_number" @click="copy(data.phone_number)" class="btn-secondary !px-2 !py-1">Salin</button>
                    </div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-slate-500">Kode OTP</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="break-all text-3xl font-black tracking-widest text-brand-600 dark:text-brand-300" x-text="data.otp_code || '———'">{{ $order->otp_code ?: '———' }}</span>
                        <button type="button" x-show="data.otp_code" @click="copy(data.otp_code)" class="btn-secondary !px-2 !py-1">Salin</button>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 p-4 dark:border-white/10">
                <div class="text-xs uppercase tracking-wider text-slate-500">Pesan status</div>
                <p class="mt-2 whitespace-pre-line text-sm" x-text="data.message || 'Menunggu pembaruan status dari provider...'">
                    {{ $order->provider_message ?: 'Menunggu pembaruan status dari provider...' }}
                </p>
            </div>

            <div class="mt-5 flex flex-col gap-2 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <div>Sisa waktu: <strong class="text-slate-900 dark:text-white" x-text="countdown">Menunggu nomor dari provider</strong></div>
                <div class="flex items-center gap-2">
                    <span class="inline-block size-2 animate-pulse rounded-full bg-emerald-400"></span>
                    <span x-text="lastChecked">Sinkron otomatis</span>
                </div>
            </div>

            <div x-show="waitingForActivation" class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-700 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300">
                Nomor sedang dialokasikan oleh provider. Halaman mengecek status otomatis setiap 3 detik. Jika tidak ingin menunggu, tombol <strong>Batalkan</strong> tetap dapat digunakan sebelum nomor tersedia.
            </div>
        </section>

        <section class="card p-6">
            <h2 class="font-bold">Ringkasan</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Status</dt>
                    <dd class="font-semibold capitalize" x-text="statusLabel">{{ str_replace('_', ' ', $order->status) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Harga</dt>
                    <dd class="font-semibold">Rp {{ number_format((float) $order->sell_price, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Dibuat</dt>
                    <dd>{{ $order->created_at->format('d M Y H:i') }}</dd>
                </div>
            </dl>

            <div class="mt-6 grid gap-2">
                <form method="post" action="{{ route('orders.action', $order) }}">
                    @csrf
                    <input type="hidden" name="action" value="ready">
                    <button x-bind:disabled="!can.ready" class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-40">SMS dikirim</button>
                </form>
                <form method="post" action="{{ route('orders.action', $order) }}">
                    @csrf
                    <input type="hidden" name="action" value="resend">
                    <button x-bind:disabled="!can.resend" class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-40">Kirim ulang</button>
                </form>
                <form method="post" action="{{ route('orders.action', $order) }}">
                    @csrf
                    <input type="hidden" name="action" value="complete">
                    <button x-bind:disabled="!can.complete" class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-40">Selesaikan</button>
                </form>
                <form method="post" action="{{ route('orders.action', $order) }}">
                    @csrf
                    <input type="hidden" name="action" value="cancel">
                    <button x-bind:disabled="!can.cancel" class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-40">Batalkan</button>
                </form>
                <form method="post" action="{{ route('orders.action', $order) }}">
                    @csrf
                    <input type="hidden" name="action" value="reactivate">
                    <button x-bind:disabled="!can.reactivate" class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-40">Aktifkan ulang</button>
                </form>
            </div>

            <p class="mt-4 text-xs leading-5 text-slate-500">
                Tombol aksi akan aktif otomatis sesuai status provider. Pembatalan dinonaktifkan setelah OTP diterima karena provider tidak mengizinkan cancel setelah kode masuk.
            </p>
        </section>
    </div>

    <p x-show="copied" x-transition class="fixed bottom-5 right-5 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl">
        Disalin ke clipboard
    </p>
</div>
@endsection
