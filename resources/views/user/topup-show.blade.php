@extends('layouts.app')
@php($title = 'Invoice Isi Saldo')
@section('content')
<div
    x-data="topupStatus(@js(route('topups.status', $topup)), @js($topup->status), @js($topup->expires_at?->toIso8601String()))"
>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a class="text-sm text-brand-600 dark:text-brand-300" href="{{ route('topups.index') }}">← Kembali</a>
            <h1 class="mt-2 text-3xl font-black">Invoice isi saldo</h1>
            <p class="mt-2 font-mono text-sm text-slate-500">{{ $topup->order_id }}</p>
            <p class="mt-1 text-xs font-bold text-violet-600 dark:text-violet-300">Penyedia pembayaran: {{ $gatewayLabel }}</p>
        </div>
        <x-status :value="$topup->status" />
    </div>

    @if($topup->status === 'failed' || filled($providerError))
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
            <div class="font-black">Pembayaran belum berhasil dibuat</div>
            <p class="mt-1 leading-6">{{ $providerError ?: 'Penyedia pembayaran mengembalikan kegagalan. Silakan buat invoice baru atau hubungi admin.' }}</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-6 text-center">
            @if($isQris && filled($paymentNumber))
                <div x-data="qrCode(@js($paymentNumber))">
                    <img x-show="src" :src="src" class="mx-auto w-64 rounded-xl bg-white p-3" alt="QR pembayaran">
                </div>
                <div class="mt-4 text-sm font-bold">Pindai QRIS untuk membayar</div>
            @elseif(filled($paymentNumber))
                <div class="mx-auto grid min-h-48 place-items-center rounded-xl border border-dashed border-slate-300 p-6 dark:border-white/10" x-data='copyText(@js($paymentNumber))'>
                    <div class="w-full">
                        <div class="text-sm text-slate-500">Nomor pembayaran</div>
                        <div class="mt-2 break-all text-2xl font-black">{{ $paymentNumber }}</div>
                        <button type="button" class="btn-secondary mt-4" @click="copy">
                            <x-icon name="copy" size="size-4" />
                            <span x-text="copied ? 'Tersalin' : 'Salin nomor'"></span>
                        </button>
                    </div>
                </div>
            @else
                <div class="mx-auto grid min-h-48 place-items-center rounded-xl border border-amber-300 bg-amber-50 p-6 text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    <div>
                        <div class="font-black">Data pembayaran belum tersedia</div>
                        <p class="mt-2 text-sm leading-6">Buat invoice baru. Tautan pembayaran asli penyedia tidak dikirim ke browser.</p>
                    </div>
                </div>
            @endif

            @if($topup->status === 'pending')
                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left dark:border-white/10 dark:bg-white/[.03]">
                    <div class="text-xs font-black uppercase tracking-wider text-slate-400">Sisa waktu pembayaran</div>
                    <div class="mt-1 font-mono text-2xl font-black text-violet-700 dark:text-violet-300" x-text="countdown">--:--</div>
                </div>
            @endif
        </section>

        <section class="card p-6">
            <h2 class="font-bold">Rincian pembayaran</h2>
            <dl class="mt-5 space-y-4 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Saldo masuk</dt>
                    <dd class="font-semibold">Rp {{ number_format((float) $topup->amount, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Biaya {{ $gatewayLabel }}</dt>
                    <dd>Rp {{ number_format((float) $topup->fee, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-200 pt-4 text-base dark:border-white/10">
                    <dt class="font-bold">Total bayar</dt>
                    <dd class="font-black text-brand-600 dark:text-brand-300">Rp {{ number_format((float) $topup->total_payment, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Kedaluwarsa</dt>
                    <dd>{{ $topup->expires_at?->format('d M Y H:i') ?: '—' }}</dd>
                </div>
            </dl>

            <div class="mt-6 rounded-xl bg-amber-500/10 p-4 text-sm leading-6 text-amber-700 dark:text-amber-300">
                Bayar sesuai total yang tertera. Saldo hanya ditambahkan setelah server memverifikasi invoice lokal dan status transaksi langsung ke {{ $gatewayLabel }}. Data callback atau URL kembali tidak pernah dipakai sendirian untuk mengkredit saldo.
            </div>
        </section>
    </div>
</div>
@push('head')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('topupStatus', (url, initial, expiresAt) => ({
        status: initial,
        expiresAt,
        countdown: '--:--',
        timer: null,
        pollTimer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
            this.pollTimer = setInterval(() => this.poll(), 5000);
        },
        tick() {
            if (!this.expiresAt) {
                this.countdown = '—';
                return;
            }

            const target = new Date(this.expiresAt).getTime();
            if (Number.isNaN(target)) {
                this.countdown = '—';
                return;
            }

            const remaining = Math.max(0, Math.floor((target - Date.now()) / 1000));
            if (remaining <= 0) {
                this.countdown = 'Kedaluwarsa';
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
                this.poll();
                return;
            }

            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            this.countdown = hours > 0
                ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
                : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },
        async poll() {
            if (!['creating', 'pending'].includes(this.status)) return;

            try {
                const response = await axios.get(url);
                const previous = this.status;
                this.status = response.data.status;
                if (response.data.expires_at) this.expiresAt = response.data.expires_at;

                if (previous !== this.status || ['completed', 'failed', 'expired', 'cancelled'].includes(this.status)) {
                    window.location.reload();
                }
            } catch (_) {
                // Polling status bersifat best-effort; invoice tetap dapat dibaca.
            }
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
            if (this.pollTimer) clearInterval(this.pollTimer);
        },
    }));
});
</script>
@endpush
@endsection
