@props(['value'])
@php
    $key = strtolower((string) $value);
    $style = match ($key) {
        'completed', 'success', 'paid', 'active', 'ready' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        'pending', 'awaiting_payment', 'processing', 'provider_pending', 'creating', 'waiting', 'info' => 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
        'expired', 'cancelled', 'failed', 'payment_failed', 'banned', 'danger' => 'bg-rose-500/10 text-rose-600 dark:text-rose-300',
        'suspended', 'warning', 'refunded', 'scheduled' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        default => 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
    };
    $label = match ($key) {
        'completed' => 'Selesai',
        'success' => 'Berhasil',
        'paid' => 'Dibayar',
        'active' => 'Aktif',
        'ready' => 'Siap',
        'pending' => 'Menunggu',
        'awaiting_payment' => 'Menunggu pembayaran',
        'payment_failed' => 'Pembayaran gagal',
        'processing' => 'Diproses',
        'provider_pending' => 'Menunggu penyedia',
        'creating' => 'Membuat transaksi',
        'waiting' => 'Menunggu',
        'info' => 'Informasi',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
        'failed' => 'Gagal',
        'banned' => 'Diblokir',
        'danger' => 'Bahaya',
        'suspended' => 'Ditangguhkan',
        'warning' => 'Peringatan',
        'refunded' => 'Dikembalikan',
        'scheduled' => 'Dijadwalkan',
        'otp_received' => 'OTP diterima',
        'credit' => 'Masuk',
        'debit' => 'Keluar',
        default => ucfirst(str_replace('_', ' ', $key)),
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$style]) }}>{{ $label }}</span>
