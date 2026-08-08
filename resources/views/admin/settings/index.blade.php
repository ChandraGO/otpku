@extends('layouts.app')
@php($title = 'Pengaturan')
@php($tabs = ['site'=>'Situs','auth'=>'Verifikasi','orders'=>'Pesanan','pricing'=>'Harga','topup'=>'Isi Saldo','payments'=>'Penyedia Pembayaran','sms_virtual'=>'SMS Virtual','pakasir'=>'Pakasir','duitku'=>'Duitku','mail'=>'SMTP','security'=>'Keamanan'])
@php($v = fn($key,$default='') => old($key, $values[$tab.'.'.$key] ?? $default))
@section('content')
<div><h1 class="section-title">Pengaturan sistem</h1></div>
<div class="scrollbar-thin mt-6 flex gap-2 overflow-x-auto pb-2">
    @foreach($tabs as $key=>$label)
        <a class="filter-chip {{ $tab===$key?'filter-chip-active':'' }}" href="{{ route('admin.settings.index',['tab'=>$key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="mt-5 grid gap-6 xl:grid-cols-[1fr_340px]">
    @if($tab === 'payments')
        <form class="card space-y-5 p-6" method="post" action="{{ route('admin.settings.payment-gateway') }}">
            @csrf
            <div>
                <h2 class="text-lg font-black">Penyedia pembayaran aktif</h2>
                <p class="mt-1 text-sm text-slate-500">Hanya satu penyedia pembayaran dapat aktif. Jika masih ada transaksi berjalan, pergantian masuk antrean dan diterapkan otomatis setelah semuanya selesai.</p>
            </div>

            @if($pendingGateway)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    <div class="font-black">Pergantian sedang dijadwalkan ke {{ ucfirst($pendingGateway) }}</div>
                    <p class="mt-1">Penyedia {{ ucfirst($activeGateway) }} tetap aktif sampai {{ (int)($gatewayBlockers['topups'] ?? 0) }} isi saldo dan {{ (int)($gatewayBlockers['orders'] ?? 0) }} pesanan aktif selesai. Selama transaksi lama diselesaikan, transaksi baru ditahan sementara.</p>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                @foreach(['pakasir'=>'Pakasir','duitku'=>'Duitku'] as $gateway=>$label)
                    <label class="relative cursor-pointer rounded-3xl border p-5 transition {{ $activeGateway===$gateway ? 'border-violet-500 bg-violet-500/10' : 'border-slate-200 dark:border-white/10' }}">
                        <div class="flex items-start gap-3">
                            <input class="mt-1 size-5 accent-violet-600" type="radio" name="active_gateway" value="{{ $gateway }}" @checked(old('active_gateway',$pendingGateway ?: $activeGateway)===$gateway)>
                            <div>
                                <div class="flex items-center gap-2 font-black">
                                    {{ $label }}
                                    @if($activeGateway===$gateway)<span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-300">Aktif</span>@endif
                                    @if($pendingGateway===$gateway)<span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-black uppercase text-amber-600 dark:text-amber-300">Menunggu</span>@endif
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ $gateway==='pakasir' ? 'Menggunakan integrasi server Pakasir yang sudah ada.' : 'Menggunakan Duitku API V2 dengan verifikasi callback dan pengecekan transaksi antarserver.' }}</p>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <div class="card-soft p-4 text-sm text-slate-500">
                Pengaman pergantian: status <strong>membuat transaksi/menunggu</strong> pada isi saldo dan seluruh pesanan yang belum terminal akan menahan pergantian. Invoice lama tetap menyimpan penyedia asalnya sehingga verifikasi tidak pernah berpindah penyedia.
            </div>

            <button class="btn-primary">Simpan penyedia pembayaran</button>
        </form>
    @else
        <form class="card space-y-5 p-6" method="post" action="{{ route('admin.settings.update') }}">
            @csrf @method('put')
            <input type="hidden" name="group" value="{{ $tab }}">

            @if($tab==='site')
                <div><label class="label">Nama situs</label><input class="input" name="name" value="{{ $v('name') }}" required></div>
                <div><label class="label">Deskripsi situs</label><textarea class="input min-h-28" name="description" required>{{ $v('description') }}</textarea><p class="mt-1 text-xs text-slate-500">Dipakai pada halaman utama, deskripsi metadata, dan halaman bantuan.</p></div>
                <div><label class="label">WhatsApp dukungan</label><input class="input" name="support_whatsapp" value="{{ $v('support_whatsapp') }}" placeholder="62812..."></div>
                <div><label class="label">URL logo bisnis</label><input class="input" type="url" name="logo_url" value="{{ $v('logo_url') }}" placeholder="https://domain.com/logo.png"><p class="mt-1 text-xs text-slate-500">Logo akan dipakai pada header, sidebar, dan footer. Kosongkan untuk memakai logo bawaan.</p>@if($v('logo_url'))<div class="mt-3 flex items-center gap-3 rounded-2xl border border-slate-200 p-3 dark:border-white/10"><img src="{{ $v('logo_url') }}" alt="Pratinjau logo" class="size-14 rounded-xl bg-white object-contain p-1"><span class="text-xs text-slate-500">Pratinjau logo aktif</span></div>@endif</div>
            @elseif($tab==='auth')
                <div><label class="label">Masa berlaku OTP email (menit)</label><input class="input" type="number" name="email_otp_expiry_minutes" min="3" max="60" value="{{ $v('email_otp_expiry_minutes',10) }}" required></div>
                <div><label class="label">Jeda kirim ulang (detik)</label><input class="input" type="number" name="email_otp_resend_seconds" min="30" max="600" value="{{ $v('email_otp_resend_seconds',60) }}" required></div>
            @elseif($tab==='orders')
                <div><label class="label">Batas bawaan kedaluwarsa pesanan (menit)</label><input class="input" type="number" name="default_expiry_minutes" min="5" max="180" value="{{ $v('default_expiry_minutes',20) }}" required></div>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 text-sm font-semibold dark:border-white/10"><input type="checkbox" name="refund_on_expired" value="1" @checked((bool)$v('refund_on_expired',false))> Pengembalian dana otomatis ketika status penyedia kedaluwarsa</label>
            @elseif($tab==='pricing')
                <div class="grid gap-4 sm:grid-cols-3"><div><label class="label">Markup persentase</label><input class="input" type="number" step="0.01" name="markup_percent" min="0" max="500" value="{{ $v('markup_percent',10) }}" required></div><div><label class="label">Biaya tetap</label><input class="input" type="number" name="fixed_fee" min="0" value="{{ $v('fixed_fee',0) }}" required></div><div><label class="label">Pembulatan harga</label><select class="input" name="round_to">@foreach([1,10,100,500,1000] as $round)<option value="{{ $round }}" @selected((int)$v('round_to',100)===$round)>Rp {{ number_format($round,0,',','.') }}</option>@endforeach</select></div></div>
                <div class="card-soft p-4 text-sm text-slate-500">Harga pengguna = harga penyedia + markup persentase + biaya tetap, kemudian dibulatkan.</div>
            @elseif($tab==='topup')
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Minimum isi saldo</label><input class="input" type="number" name="minimum" value="{{ $v('minimum',10000) }}" required></div><div><label class="label">Maksimum isi saldo</label><input class="input" type="number" name="maximum" value="{{ $v('maximum',5000000) }}" required></div></div>
                <div class="card-soft p-4 text-sm text-slate-500">Saat Duitku aktif, minimum efektif otomatis tidak akan kurang dari Rp 10.000 mengikuti batas minimum API Duitku.</div>
            @elseif($tab==='sms_virtual')
                <div><label class="label">URL dasar</label><input class="input" type="url" name="base_url" value="{{ $v('base_url','https://api.sms-virtuals.net') }}" required></div>
                <div><label class="label">API key</label><x-password-input name="api_key" value="" placeholder="Kosongkan untuk mempertahankan API key saat ini" /><p class="mt-1 text-xs text-slate-500">Disimpan terenkripsi dan hanya dipakai pada permintaan server.</p></div>
                <div><label class="label">Batas waktu (detik)</label><input class="input" type="number" name="timeout" min="5" max="120" value="{{ $v('timeout',30) }}" required></div>
                <div class="border-t border-slate-200 pt-5 dark:border-white/10"><h2 class="font-black">Pengawasan saldo penyedia</h2></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Batas saldo minimum</label><input class="input" type="number" name="low_balance_threshold" min="0" value="{{ $v('low_balance_threshold',5000) }}" required></div><div><label class="label">Buffer cadangan (%)</label><input class="input" type="number" step="0.01" name="reserve_buffer_percent" min="0" max="500" value="{{ $v('reserve_buffer_percent',20) }}" required></div></div>
            @elseif($tab==='pakasir')
                <div><label class="label">URL dasar</label><input class="input" type="url" name="base_url" value="{{ $v('base_url','https://app.pakasir.com') }}" required><p class="mt-1 text-xs text-slate-500">Gunakan host resmi tanpa /api.</p></div>
                <div><label class="label">Slug proyek</label><input class="input" name="project" value="{{ $v('project') }}" required></div>
                <div><label class="label">API key</label><x-password-input name="api_key" value="" placeholder="Kosongkan untuk mempertahankan API key saat ini" /></div>
                <div><label class="label">Metode bawaan</label><input class="input" name="payment_method" value="{{ $v('payment_method','qris') }}" required></div>
            @elseif($tab==='duitku')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Lingkungan</label><select class="input" name="environment"><option value="production" @selected($v('environment','production')==='production')>Produksi</option><option value="sandbox" @selected($v('environment')==='sandbox')>Sandbox</option></select></div>
                    <div><label class="label">Merchant Code</label><input class="input" name="merchant_code" value="{{ $v('merchant_code') }}" placeholder="DXXXX" required></div>
                </div>
                <div><label class="label">API Key</label><x-password-input name="api_key" value="" placeholder="Kosongkan untuk mempertahankan API key saat ini" /><p class="mt-1 text-xs text-slate-500">API key disimpan terenkripsi dan tidak dikirim ke browser pengguna.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Metode pembayaran Duitku</label><select class="input" name="payment_method">@foreach($duitkuMethods as $code=>$label)<option value="{{ $code }}" @selected(strtoupper((string)$v('payment_method','NQ'))===$code)>{{ $label }} ({{ $code }})</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Hanya metode ini yang ditawarkan ketika Duitku aktif. Pastikan kanal sudah aktif di merchant Duitku.</p></div>
                    <div><label class="label">Kedaluwarsa (menit)</label><input class="input" type="number" name="expiry_minutes" min="5" max="1440" value="{{ $v('expiry_minutes',10) }}" required></div>
                </div>
                <div class="card-soft p-4 text-sm text-slate-500">Integrasi memakai API V2. Callback tidak langsung mengkredit saldo; server selalu melakukan cek transaksi ke Duitku dan mencocokkan ID pesanan, nominal, referensi, serta status.</div>
            @elseif($tab==='mail')
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Pengirim email</label><select class="input" name="mailer"><option value="smtp" @selected($v('mailer','smtp')==='smtp')>SMTP</option><option value="log" @selected($v('mailer')==='log')>Hanya catat log</option></select></div><div><label class="label">Host SMTP</label><input class="input" name="host" value="{{ $v('host') }}" required></div><div><label class="label">Porta</label><input class="input" type="number" name="port" value="{{ $v('port',587) }}" required></div><div><label class="label">Enkripsi</label><select class="input" name="encryption"><option value="" @selected($v('encryption')==='')>Tanpa enkripsi</option><option value="tls" @selected($v('encryption','tls')==='tls')>TLS / STARTTLS</option><option value="ssl" @selected($v('encryption')==='ssl')>SSL</option></select></div><div><label class="label">Nama pengguna</label><input class="input" name="username" value="{{ $v('username') }}"></div><div><label class="label">Kata sandi</label><x-password-input name="password" value="" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan kata sandi" /></div><div><label class="label">Alamat pengirim</label><input class="input" type="email" name="from_address" value="{{ $v('from_address') }}" required></div><div><label class="label">Nama pengirim</label><input class="input" name="from_name" value="{{ $v('from_name') }}" required></div></div>
            @elseif($tab==='security')
                <div><label class="label">Rahasia webhook SMS Virtual</label><x-password-input name="provider_webhook_secret" value="" placeholder="Minimal 24 karakter; kosongkan untuk mempertahankan" /></div>
            @endif

            <button class="btn-primary">Simpan {{ $tabs[$tab] }}</button>
        </form>
    @endif

    <aside class="space-y-4">
        <section class="card p-5">
            <h2 class="font-black">Tes & sinkronisasi</h2>
            <div class="mt-4 grid gap-2">
                <form method="post" action="{{ route('admin.settings.test-sms') }}">@csrf<button class="btn-secondary w-full justify-between">Tes saldo SMS Virtual <x-icon name="arrow-right" size="size-4" /></button></form>
                <form method="post" action="{{ route('admin.settings.sync-catalog') }}">@csrf<button class="btn-secondary w-full justify-between">Sinkronkan katalog <x-icon name="arrow-right" size="size-4" /></button></form>
                <form method="post" action="{{ route('admin.settings.test-pakasir') }}">@csrf<button class="btn-secondary w-full justify-between">Tes konfigurasi Pakasir <x-icon name="arrow-right" size="size-4" /></button></form>
                <form method="post" action="{{ route('admin.settings.test-duitku') }}">@csrf<button class="btn-secondary w-full justify-between">Tes koneksi Duitku <x-icon name="arrow-right" size="size-4" /></button></form>
                <form method="post" action="{{ route('admin.settings.test-mail') }}">@csrf<button class="btn-secondary w-full justify-between">Kirim email uji <x-icon name="arrow-right" size="size-4" /></button></form>
            </div>
        </section>
        <section class="card p-5 text-sm leading-6 text-slate-500">
            <div class="flex items-center gap-2"><x-icon name="shield" class="text-emerald-500" /><h2 class="font-black text-slate-800 dark:text-slate-100">Catatan keamanan</h2></div>
            <p class="mt-2">API key, signature, request transaksi, dan cek status penyedia hanya berjalan di backend. Frontend hanya menerima data pembayaran yang memang dibutuhkan pengguna seperti QR string atau nomor VA.</p>
        </section>
    </aside>
</div>
@endsection
