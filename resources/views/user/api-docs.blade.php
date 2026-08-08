@extends('layouts.app')
@php($title = 'Dokumentasi API')
@php($apiBase = url('/api/v1'))
@section('content')
<div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-slate-950 via-violet-950 to-cyan-950 p-7 text-white shadow-xl sm:p-9">
    <div class="absolute -right-20 -top-24 size-72 rounded-full bg-cyan-400/15 blur-3xl"></div>
    <div class="relative max-w-3xl">
        <span class="badge bg-white/10 text-cyan-200">API Pelanggan Publik v1</span>
        <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Integrasi API</h1>
        <div class="mt-6 flex flex-wrap gap-3"><a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-black text-violet-700">Kelola API Key <x-icon name="arrow-right" size="size-4" /></a><span class="inline-flex items-center rounded-2xl border border-white/20 bg-white/10 px-4 py-3 font-mono text-xs text-white/80">{{ $apiBase }}</span></div>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_330px]">
    <div class="space-y-6">
        <section class="card p-6">
            <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="shield" /></span><div><h2 class="text-lg font-black">Autentikasi</h2><p class="mt-1 text-sm leading-6 text-slate-500">Semua permintaan wajib memakai HTTPS dan header <code class="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-white/10">x-api-key</code>.</p></div></div>
            <pre class="mt-5 overflow-x-auto rounded-2xl bg-slate-950 p-5 text-xs leading-6 text-slate-200"><code>curl "{{ $apiBase }}/me" \
  -H "Accept: application/json" \
  -H "x-api-key: YOUR_API_KEY"</code></pre>
            <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">Jangan menaruh API key di sisi klien atau peramban publik. Simpan sebagai variabel lingkungan pada server bot Telegram Anda.</div>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-slate-200 p-6 dark:border-white/10"><h2 class="text-lg font-black">Endpoint</h2><p class="mt-1 text-sm text-slate-500">Respons konsisten memakai format <code>{ success, message, data }</code>.</p></div>
            <div class="divide-y divide-slate-200 dark:divide-white/10">
                @foreach([
                    ['GET', '/me', 'Profil, saldo, Telegram ID, dan negara bawaan.'],
                    ['GET', '/balance', 'Saldo akun terbaru dalam Rupiah.'],
                    ['GET', '/countries?q=indo', 'Daftar negara aktif yang memiliki stok.'],
                    ['GET', '/services?country_id=1&q=telegram', 'Daftar layanan, harga terendah, dan total stok.'],
                    ['GET', '/prices?service_id=1&country_id=1', 'Varian harga yang dapat dipesan. Gunakan price_id pada pesanan.'],
                    ['GET', '/orders?limit=20', 'Riwayat pesanan pengguna.'],
                    ['POST', '/orders', 'Buat pesanan baru dengan price_id dan idempotency_key UUID.'],
                    ['GET', '/orders/{order_id}', 'Ambil status terbaru, nomor, SMS, dan OTP.'],
                    ['POST', '/orders/{order_id}/actions', 'Aksi siap (ready), kirim ulang (resend), batalkan (cancel), selesaikan (complete), atau aktifkan kembali (reactivate).'],
                ] as $endpoint)
                    <article class="grid gap-3 p-5 sm:grid-cols-[76px_1fr] sm:items-start"><span class="badge {{ $endpoint[0] === 'GET' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300' : 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' }} justify-center">{{ $endpoint[0] }}</span><div><code class="break-all text-sm font-black">{{ $endpoint[1] }}</code><p class="mt-1 text-sm leading-6 text-slate-500">{{ $endpoint[2] }}</p></div></article>
                @endforeach
            </div>
        </section>

        <section class="card p-6">
            <h2 class="text-lg font-black">Membuat pesanan</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Gunakan UUID unik sebagai <code>idempotency_key</code>. Pengulangan permintaan dengan key yang sama tidak akan membuat debit ganda.</p>
            <pre class="mt-5 overflow-x-auto rounded-2xl bg-slate-950 p-5 text-xs leading-6 text-slate-200"><code>curl -X POST "{{ $apiBase }}/orders" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "x-api-key: YOUR_API_KEY" \
  -d '{
    "price_id": 123,
    "idempotency_key": "550e8400-e29b-41d4-a716-446655440000"
  }'</code></pre>
        </section>

        <section class="card p-6">
            <h2 class="text-lg font-black">Contoh bot Telegram (PHP)</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Contoh sederhana untuk membaca status pesanan dari server bot. API key disimpan sebagai variabel lingkungan.</p>
            <pre class="mt-5 overflow-x-auto rounded-2xl bg-slate-950 p-5 text-xs leading-6 text-slate-200"><code>&lt;?php
$apiKey = getenv('OTP_API_KEY');
$orderId = $argv[1] ?? '';

$ch = curl_init('{{ $apiBase }}/orders/' . rawurlencode($orderId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_HTTPHEADER =&gt; [
        'Accept: application/json',
        'x-api-key: ' . $apiKey,
    ],
]);

$response = json_decode(curl_exec($ch), true);
if (!($response['success'] ?? false)) {
    throw new RuntimeException($response['message'] ?? 'API gagal');
}

$order = $response['data'];
$message = "Status: {$order['status']}\n" .
           "Nomor: " . ($order['phone_number'] ?? '-') . "\n" .
           "OTP: " . ($order['otp_code'] ?? '-');

// Kirim $message melalui Telegram Bot API dari server Anda.</code></pre>
        </section>

        <section class="card p-6">
            <h2 class="text-lg font-black">Status HTTP dan Kesalahan</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach([
                    ['200', 'Permintaan berhasil.'], ['202', 'Pesanan diterima dan diproses.'], ['401', 'API key hilang, salah, atau sudah dirotasi.'], ['403', 'Akun tidak aktif.'], ['409', 'Pesanan belum siap untuk aksi.'], ['422', 'Validasi, saldo, stok, atau aksi gagal.'], ['429', 'Batas permintaan terlampaui.'],
                ] as $status)
                    <div class="card-soft flex gap-3 p-4"><code class="font-black text-violet-600 dark:text-violet-300">{{ $status[0] }}</code><span class="text-sm text-slate-500">{{ $status[1] }}</span></div>
                @endforeach
            </div>
        </section>
    </div>

    <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
        <section class="card p-5"><h2 class="font-black">API Key Anda</h2><code class="mt-3 block break-all rounded-2xl bg-slate-100 p-4 text-xs font-bold dark:bg-white/5">{{ auth()->user()->api_key ?: 'Belum dibuat' }}</code><a href="{{ route('profile.edit') }}" class="btn-secondary mt-4 w-full">Rotasi API Key</a></section>
        <section class="card p-5"><h2 class="font-black">Batas penggunaan</h2><p class="mt-2 text-sm leading-6 text-slate-500">Maksimum 120 permintaan per menit per akun. Periksa status setiap 5–10 detik agar stabil dan tidak membebani penyedia.</p></section>
        <section class="card p-5"><h2 class="font-black">Alur Telegram</h2><ol class="mt-3 space-y-3 text-sm leading-6 text-slate-500"><li>1. Cari layanan dan harga.</li><li>2. Buat pesanan dengan UUID unik.</li><li>3. Simpan ID pesanan (order_id).</li><li>4. Periksa endpoint detail secara berkala.</li><li>5. Kirim nomor/OTP ke chat pengguna.</li></ol></section>
    </aside>
</div>
@endsection
