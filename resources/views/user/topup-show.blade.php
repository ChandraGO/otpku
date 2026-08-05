@extends('layouts.app')
@php($title = 'Invoice Top Up')
@section('content')
<div x-data="topupStatus(@js(route('topups.status', $topup)), @js($topup->status))">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a class="text-sm text-brand-600 dark:text-brand-300" href="{{ route('topups.index') }}">← Kembali</a>
            <h1 class="mt-2 text-3xl font-black">Invoice top up</h1>
            <p class="mt-2 font-mono text-sm text-slate-500">{{ $topup->order_id }}</p>
        </div>
        <x-status :value="$topup->status" />
    </div>

    @if($topup->status === 'failed' || filled($providerError))
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
            <div class="font-black">Pembayaran belum berhasil dibuat</div>
            <p class="mt-1 leading-6">{{ $providerError ?: 'Provider pembayaran mengembalikan kegagalan. Silakan buat invoice baru atau hubungi admin.' }}</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-6 text-center">
            @if($topup->payment_method === 'qris' && filled($paymentNumber))
                <div x-data="qrCode(@js($paymentNumber))">
                    <img x-show="src" :src="src" class="mx-auto w-64 rounded-xl bg-white p-3" alt="QR pembayaran">
                </div>
            @else
                <div class="mx-auto grid min-h-48 place-items-center rounded-xl border border-dashed border-slate-300 p-6 dark:border-white/10">
                    <div>
                        <div class="text-sm text-slate-500">Nomor pembayaran</div>
                        <div class="mt-2 break-all text-2xl font-black">{{ $paymentNumber ?: 'Buka halaman pembayaran Pakasir' }}</div>
                    </div>
                </div>
            @endif

            @if($topup->checkout_url && $topup->status !== 'failed')
                <a target="_blank" rel="noopener" class="btn-primary mt-5 w-full" href="{{ $topup->checkout_url }}">
                    Bayar melalui Pakasir
                </a>
            @endif

            @if(! filled($paymentNumber) && $topup->status === 'pending')
                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Data QR/VA tidak tersedia di invoice lokal. Gunakan tombol Pakasir di atas; status tetap diverifikasi oleh server.
                </p>
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
                    <dt class="text-slate-500">Biaya Pakasir</dt>
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
                Bayar sesuai total yang tertera. Saldo hanya ditambahkan setelah server memverifikasi order ID, project, nominal, dan status transaksi ke Pakasir.
            </div>
        </section>
    </div>
</div>
@push('head')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('topupStatus', (url, initial) => ({
        status: initial,
        timer: null,
        init() {
            this.timer = setInterval(async () => {
                if (!['creating', 'pending'].includes(this.status)) return;

                try {
                    const response = await axios.get(url);
                    const previous = this.status;
                    this.status = response.data.status;

                    if (previous !== this.status || ['completed', 'failed', 'expired', 'cancelled'].includes(this.status)) {
                        window.location.reload();
                    }
                } catch (error) {
                    // Status polling is best-effort; the page remains usable.
                }
            }, 5000);
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    }));
});
</script>
@endpush
@endsection
