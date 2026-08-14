@extends('layouts.app')
@php
    $title = 'Detail Pesanan';
    $hasActivation = filled($order->provider_activation_id);
    $terminal = $order->isTerminal();
    $hasOtp = $order->hasOtp();
    $canCancelLocally = ! $hasActivation && in_array($order->status, ['processing', 'provider_pending'], true);
    $canCancelPayment = $order->payment_channel === 'paykita' && $order->payment_status === 'pending' && $order->status === 'awaiting_payment' && filled($order->paykita_order_id);
    $initialPayload = [
        'status' => $order->status,
        'payment_channel' => $order->payment_channel,
        'payment_status' => $order->payment_status,
        'payment_pay_amount' => $order->payment_pay_amount,
        'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
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
            'cancel' => $canCancelPayment || $canCancelLocally || ($hasActivation && ! $terminal && ! $hasOtp),
            'reactivate' => $hasActivation && in_array($order->status, ['cancelled', 'expired', 'failed'], true),
        ],
    ];
    $initialPayloadEncoded = base64_encode(json_encode($initialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
    $initialStatusLabel = match ($order->status) {
        'awaiting_payment' => 'Menunggu pembayaran',
        'payment_failed' => 'Pembayaran gagal',
        'processing' => 'Diproses',
        'provider_pending' => 'Menunggu penyedia',
        'pending' => 'Menunggu',
        'ready' => 'Siap',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
        'refunded' => 'Dikembalikan',
        'failed' => 'Gagal',
        default => ucfirst(str_replace('_', ' ', $order->status)),
    };
@endphp

@section('content')
<div
    x-data="orderStatus"
    data-status-url="{{ route('orders.status', $order) }}"
    data-initial="{{ $initialPayloadEncoded }}"
>
    <div class="mx-auto max-w-6xl">
        <a class="inline-flex items-center gap-2 text-sm font-semibold text-brand-600 hover:underline dark:text-brand-300" href="{{ route('orders.index') }}">
            <span aria-hidden="true">←</span> Kembali ke pesanan
        </a>

        <header class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="break-words text-2xl font-black tracking-tight sm:text-3xl">{{ $order->service_name }}</h1>
                <p class="mt-1.5 text-sm text-slate-500">{{ $order->country_name }} · {{ $order->operator_name ?: 'Semua operator' }}</p>
            </div>
            <span
                class="badge w-fit shrink-0 bg-sky-500/10 text-sky-600 dark:text-sky-300"
                :class="paymentExpired ? '!bg-amber-500/10 !text-amber-700 dark:!text-amber-300' : ''"
                x-text="statusLabel"
            >{{ $initialStatusLabel }}</span>
        </header>

        @if($order->payment_channel === 'paykita' && $order->payment_status !== 'paid')
            <section
                x-show="data.payment_status !== 'paid'"
                class="card mt-5 overflow-hidden !p-0"
            >
                <div class="grid lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div class="p-5 sm:p-6 lg:p-7">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-violet-500/10 px-3 py-1 text-xs font-black uppercase tracking-wider text-violet-700 dark:text-violet-300">Bayar Langsung</span>
                            <span
                                x-show="paymentActive"
                                x-cloak
                                class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300"
                            >QRIS aktif</span>
                            <span
                                x-show="paymentExpired"
                                x-cloak
                                class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-300"
                            >QRIS kedaluwarsa</span>
                        </div>

                        <h2 class="mt-4 text-xl font-black sm:text-2xl">Selesaikan pembayaran pesanan</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Scan QRIS di halaman ini. Setelah pembayaran terkonfirmasi, nomor akan diproses otomatis tanpa perlu berpindah halaman.
                        </p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-white/10">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Harga produk</div>
                                <div class="mt-1 text-lg font-black">Rp {{ number_format((float) $order->sell_price, 0, ',', '.') }}</div>
                            </div>
                            <div class="rounded-2xl border border-violet-200 bg-violet-50/70 p-4 dark:border-violet-400/20 dark:bg-violet-400/10">
                                <div class="text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-300">Total bayar</div>
                                <div class="mt-1 text-2xl font-black text-violet-700 dark:text-violet-200">Rp {{ number_format((float) ($order->payment_pay_amount ?: $order->sell_price), 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div
                            class="mt-4 rounded-2xl border p-4 sm:p-5"
                            :class="paymentExpired
                                ? 'border-amber-300 bg-amber-50 dark:border-amber-400/20 dark:bg-amber-400/10'
                                : 'border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5'"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="paymentExpired ? 'Status pembayaran' : 'Selesaikan dalam'">Selesaikan dalam</div>
                                    <div
                                        class="mt-1 font-mono text-3xl font-black tabular-nums sm:text-4xl"
                                        :class="paymentExpired ? 'text-amber-700 dark:text-amber-300' : 'text-slate-950 dark:text-white'"
                                        x-text="paymentCountdown"
                                    >--:--</div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <div class="text-xs text-slate-500">Batas pembayaran</div>
                                    <div class="mt-1 text-sm font-bold" x-text="paymentDeadlineLabel">{{ $order->payment_expires_at?->format('d M Y H:i:s') ?: '—' }}</div>
                                </div>
                            </div>
                            <p x-show="paymentExpired" x-cloak class="mt-3 text-sm font-semibold leading-6 text-amber-700 dark:text-amber-300">
                                Waktu pembayaran sudah habis. Jangan scan QRIS ini. Status akan diperbarui otomatis.
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 bg-slate-50/70 p-5 dark:border-white/10 dark:bg-white/[0.03] lg:border-l lg:border-t-0 lg:p-6">
                        @if(filled($order->payment_qris))
                            <div x-data="qrCode(@js($order->payment_qris))" class="mx-auto max-w-[280px] text-center">
                                <div x-show="paymentActive" class="rounded-3xl border border-slate-200 bg-white p-3 shadow-sm dark:border-white/10">
                                    <img x-show="src" :src="src" class="mx-auto aspect-square w-full rounded-2xl bg-white" alt="QRIS pembayaran">
                                </div>

                                <div
                                    x-show="paymentExpired"
                                    x-cloak
                                    class="grid aspect-square place-items-center rounded-3xl border border-dashed border-amber-300 bg-amber-50 p-6 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300"
                                >
                                    <div>
                                        <div class="mx-auto grid size-14 place-items-center rounded-full bg-amber-500/10 text-2xl">⌛</div>
                                        <div class="mt-4 text-lg font-black">QRIS kedaluwarsa</div>
                                        <p class="mt-2 text-xs leading-5">Pembayaran pada kode ini sudah tidak aktif.</p>
                                    </div>
                                </div>

                                <div x-show="paymentActive" class="mt-3 text-sm font-bold text-slate-700 dark:text-slate-200">Scan QRIS untuk membayar</div>
                                <div x-show="paymentActive" class="mt-1 text-xs leading-5 text-slate-500">Gunakan aplikasi bank atau e-wallet yang mendukung QRIS.</div>
                            </div>
                        @else
                            <div class="grid min-h-56 place-items-center rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-white/15">
                                QRIS sedang disiapkan. Halaman akan memperbarui otomatis.
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
            <section class="card p-5 sm:p-6">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-4 sm:p-5 dark:border-white/10">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Nomor</div>
                        <div class="mt-2 flex min-h-11 items-center justify-between gap-3">
                            <span class="min-w-0 break-all text-xl font-black sm:text-2xl" x-text="data.phone_number || 'Sedang disiapkan...'">{{ $order->phone_number ?: 'Sedang disiapkan...' }}</span>
                            <button type="button" x-show="data.phone_number" x-cloak @click="copy(data.phone_number)" class="btn-secondary shrink-0 !px-3 !py-2 text-xs">Salin</button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4 sm:p-5 dark:border-brand-400/20 dark:bg-brand-400/5">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Kode OTP</div>
                        <div class="mt-2 flex min-h-11 items-center justify-between gap-3">
                            <span class="min-w-0 break-all text-2xl font-black tracking-widest text-brand-600 sm:text-3xl dark:text-brand-300" x-text="data.otp_code || '———'">{{ $order->otp_code ?: '———' }}</span>
                            <button type="button" x-show="data.otp_code" x-cloak @click="copy(data.otp_code)" class="btn-secondary shrink-0 !px-3 !py-2 text-xs">Salin</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 p-4 sm:p-5 dark:border-white/10">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Pesan status</div>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <span class="inline-block size-2 animate-pulse rounded-full bg-emerald-400"></span>
                            <span x-text="lastChecked">Sinkron otomatis</span>
                        </div>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6" x-text="data.message || 'Menunggu pembaruan status...'">
                        {{ $order->provider_message ?: 'Menunggu pembaruan status...' }}
                    </p>
                </div>

                <div x-show="data.payment_status === 'paid'" x-cloak class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-white/5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-slate-500">Sisa waktu nomor</span>
                        <strong class="font-mono tabular-nums text-slate-900 dark:text-white" x-text="countdown">Menunggu nomor dari penyedia</strong>
                    </div>
                </div>

                <div x-show="waitingForActivation" x-cloak class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-700 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300">
                    Nomor sedang dialokasikan. Status diperiksa otomatis setiap 3 detik. Tombol <strong>Batalkan</strong> dapat digunakan selama nomor belum tersedia.
                </div>
            </section>

            <aside class="card p-5 sm:p-6 lg:sticky lg:top-24">
                <h2 class="text-lg font-black">Ringkasan</h2>
                <dl class="mt-4 divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="text-right font-bold" x-text="statusLabel">{{ $initialStatusLabel }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-slate-500">Harga</dt>
                        <dd class="text-right font-bold">Rp {{ number_format((float) $order->sell_price, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-3 last:pb-0">
                        <dt class="text-slate-500">Dibuat</dt>
                        <dd class="text-right">{{ $order->created_at->format('d M Y H:i') }}</dd>
                    </div>
                </dl>

                <h3 class="mt-6 text-sm font-black uppercase tracking-wider text-slate-400">Aksi pesanan</h3>
                <div class="mt-3 grid grid-cols-2 gap-2">
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
                        <button x-bind:disabled="!can.complete" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-40">Selesaikan</button>
                    </form>
                    <form method="post" action="{{ route('orders.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="reactivate">
                        <button x-bind:disabled="!can.reactivate" class="btn-secondary w-full disabled:cursor-not-allowed disabled:opacity-40">Aktifkan ulang</button>
                    </form>
                    <form class="col-span-2" method="post" action="{{ route('orders.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="cancel">
                        <button x-bind:disabled="!can.cancel || paymentExpired" class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-40">Batalkan</button>
                    </form>
                </div>

                <p class="mt-4 text-xs leading-5 text-slate-500">
                    Aksi aktif otomatis sesuai status. Pembatalan dinonaktifkan setelah OTP diterima atau waktu pembayaran sudah habis.
                </p>
            </aside>
        </div>
    </div>

    <p x-show="copied" x-cloak x-transition class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl sm:left-auto sm:right-5 sm:translate-x-0">
        Disalin ke papan klip
    </p>
</div>
@endsection
