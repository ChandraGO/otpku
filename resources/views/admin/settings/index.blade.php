@extends('layouts.app')
@php
    $title = 'Pengaturan';
    $tabs = [
        'site' => ['Situs & SEO', 'globe', 'Identitas bisnis, favicon, thumbnail berbagi, dan metadata mesin pencarian.'],
        'auth' => ['Verifikasi', 'shield', 'Atur masa berlaku OTP email dan jeda kirim ulang.'],
        'orders' => ['Pesanan', 'orders', 'Atur batas kedaluwarsa dan kebijakan pengembalian pesanan.'],
        'pricing' => ['Harga', 'chart', 'Markup, biaya tetap, dan pembulatan harga layanan.'],
        'topup' => ['Isi Saldo', 'wallet', 'Batas minimum dan maksimum isi saldo pelanggan.'],
        'payments' => ['Penyedia Pembayaran', 'topup', 'Pilih Pakasir atau Duitku dan kelola konfigurasi pada satu tempat.'],
        'sms_virtual' => ['SMS Virtual', 'bolt', 'Koneksi provider, timeout, dan pengawasan saldo penyedia.'],
        'mail' => ['SMTP', 'mail', 'Pengiriman email sistem dan konfigurasi server SMTP.'],
        'security' => ['Keamanan', 'shield', 'Rahasia webhook dan pengamanan integrasi backend.'],
    ];
    $v = fn($key,$default='') => old($key, $values[$tab.'.'.$key] ?? $default);
    $pv = fn($key,$default='') => old('pakasir.'.$key, $pakasirValues['pakasir.'.$key] ?? $default);
    $dv = fn($key,$default='') => old('duitku.'.$key, $duitkuValues['duitku.'.$key] ?? $default);
    $activeLogoUrl = (string) ($values['site.logo_url'] ?? '');
    $activeSeoImageUrl = (string) ($values['site.seo_image_url'] ?? '');
    $logoUrlInput = old('logo_url', \Illuminate\Support\Str::startsWith($activeLogoUrl, ['http://', 'https://']) ? $activeLogoUrl : '');
    $seoImageUrlInput = old('seo_image_url', \Illuminate\Support\Str::startsWith($activeSeoImageUrl, ['http://', 'https://']) ? $activeSeoImageUrl : '');
@endphp
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        @if($tab)
            <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-violet-600 hover:text-violet-500 dark:text-violet-300">
                <span aria-hidden="true">←</span> Kembali ke semua pengaturan
            </a>
            <h1 class="section-title mt-2">{{ $tabs[$tab][0] }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $tabs[$tab][2] }}</p>
        @else
            <h1 class="section-title">Pengaturan sistem</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Pilih pengaturan seperti membuka kategori di perpustakaan. Tidak perlu menggeser deretan tab panjang.</p>
        @endif
    </div>
</div>

@if(!$tab)
    <section class="card mt-7 p-6">
        <h2 class="text-lg font-black">Pilih kategori pengaturan</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($tabs as $key=>$item)
                <a href="{{ route('admin.settings.index',['tab'=>$key]) }}" class="group card-soft p-5 transition hover:-translate-y-0.5 hover:border-violet-400/70 hover:shadow-lg hover:shadow-violet-500/10">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700 transition group-hover:bg-violet-600 group-hover:text-white dark:bg-violet-500/15 dark:text-violet-300">
                            @switch($key)
                                @case('site') <x-icon name="globe" size="size-6" /> @break
                                @case('auth') <x-icon name="shield" size="size-6" /> @break
                                @case('orders') <x-icon name="orders" size="size-6" /> @break
                                @case('pricing') <x-icon name="chart" size="size-6" /> @break
                                @case('topup') <x-icon name="wallet" size="size-6" /> @break
                                @case('payments') <x-icon name="topup" size="size-6" /> @break
                                @case('sms_virtual') <x-icon name="bolt" size="size-6" /> @break
                                @case('mail') <x-icon name="mail" size="size-6" /> @break
                                @default <x-icon name="settings" size="size-6" />
                            @endswitch
                        </span>
                        <x-icon name="arrow-right" class="mt-2 text-slate-400 transition group-hover:translate-x-1 group-hover:text-violet-500" />
                    </div>
                    <h3 class="mt-4 font-black">{{ $item[0] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $item[2] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@else
<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_340px]">
    @if($tab === 'payments')
        <form
            class="card space-y-6 p-6"
            method="post"
            action="{{ route('admin.settings.payment-gateway') }}"
            x-data="{ gateway: @js(old('active_gateway', $pendingGateway ?: $activeGateway)) }"
        >
            @csrf
            <div>
                <h2 class="text-lg font-black">Penyedia pembayaran</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Klik provider yang ingin dipakai. Hanya konfigurasi provider yang dipilih yang ditampilkan dan saat disimpan provider tersebut menjadi pilihan untuk invoice baru.</p>
            </div>

            @if($pendingGateway)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                    <div class="font-black">Pergantian sedang dijadwalkan ke {{ ucfirst($pendingGateway) }}</div>
                    <p class="mt-1 leading-6">{{ ucfirst($activeGateway) }} tetap aktif sampai {{ (int)($gatewayBlockers['topups'] ?? 0) }} isi saldo dan {{ (int)($gatewayBlockers['orders'] ?? 0) }} pesanan aktif selesai. Ini mencegah transaksi lama berpindah provider di tengah proses.</p>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach(['pakasir'=>['Pakasir','QRIS dan kanal Pakasir'], 'duitku'=>['Duitku','API V2 Duitku']] as $gateway=>$meta)
                    <label
                        class="relative cursor-pointer rounded-3xl border p-5 transition"
                        :class="gateway === '{{ $gateway }}' ? 'border-violet-500 bg-violet-500/10 shadow-lg shadow-violet-500/10' : 'border-slate-200 dark:border-white/10'"
                    >
                        <input class="sr-only" type="radio" name="active_gateway" value="{{ $gateway }}" x-model="gateway">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 font-black">
                                    {{ $meta[0] }}
                                    @if($activeGateway===$gateway)
                                        <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-300">Aktif sekarang</span>
                                    @endif
                                    @if($pendingGateway===$gateway)
                                        <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-black uppercase text-amber-600 dark:text-amber-300">Menunggu</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ $meta[1] }}</p>
                            </div>
                            <span class="grid size-6 place-items-center rounded-full border-2" :class="gateway === '{{ $gateway }}' ? 'border-violet-500 bg-violet-500 text-white' : 'border-slate-300 dark:border-white/20'">
                                <x-icon name="check" size="size-4" x-show="gateway === '{{ $gateway }}'" />
                            </span>
                        </div>
                    </label>
                @endforeach
            </div>

            <div x-show="gateway === 'pakasir'" x-cloak class="space-y-5 rounded-3xl border border-slate-200 p-5 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-300"><x-icon name="topup" /></span>
                    <div><h3 class="font-black">Konfigurasi Pakasir</h3><p class="text-xs text-slate-500">Pengaturan ini muncul saat tombol Pakasir dipilih.</p></div>
                </div>
                <div><label class="label">URL dasar</label><input class="input" type="url" name="pakasir[base_url]" value="{{ $pv('base_url','https://app.pakasir.com') }}"><p class="mt-1 text-xs text-slate-500">Gunakan host resmi tanpa /api.</p></div>
                <div><label class="label">Slug proyek</label><input class="input" name="pakasir[project]" value="{{ $pv('project') }}"></div>
                <div><label class="label">API key</label><x-password-input name="pakasir[api_key]" value="" placeholder="Kosongkan untuk mempertahankan API key saat ini" /></div>
                <div><label class="label">Metode bawaan</label><input class="input" name="pakasir[payment_method]" value="{{ $pv('payment_method','qris') }}"></div>
            </div>

            <div x-show="gateway === 'duitku'" x-cloak class="space-y-5 rounded-3xl border border-slate-200 p-5 dark:border-white/10">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-300"><x-icon name="topup" /></span>
                    <div><h3 class="font-black">Konfigurasi Duitku</h3><p class="text-xs text-slate-500">Pengaturan ini muncul saat tombol Duitku dipilih.</p></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Lingkungan</label><select class="input" name="duitku[environment]"><option value="production" @selected($dv('environment','production')==='production')>Produksi</option><option value="sandbox" @selected($dv('environment')==='sandbox')>Sandbox</option></select></div>
                    <div><label class="label">Merchant Code</label><input class="input" name="duitku[merchant_code]" value="{{ $dv('merchant_code') }}" placeholder="DXXXX"></div>
                </div>
                <div><label class="label">API Key</label><x-password-input name="duitku[api_key]" value="" placeholder="Kosongkan untuk mempertahankan API key saat ini" /><p class="mt-1 text-xs text-slate-500">API key tetap disimpan terenkripsi di backend.</p></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Metode pembayaran</label><select class="input" name="duitku[payment_method]">@foreach($duitkuMethods as $code=>$label)<option value="{{ $code }}" @selected(strtoupper((string)$dv('payment_method','NQ'))===$code)>{{ $label }} ({{ $code }})</option>@endforeach</select></div>
                    <div><label class="label">Kedaluwarsa (menit)</label><input class="input" type="number" name="duitku[expiry_minutes]" min="5" max="1440" value="{{ $dv('expiry_minutes',10) }}"></div>
                </div>
                <div class="card-soft p-4 text-sm leading-6 text-slate-500">Callback tidak langsung mengkredit saldo; server tetap mencocokkan invoice, nominal, reference, dan status transaksi ke Duitku.</div>
            </div>

            <div class="card-soft p-4 text-sm leading-6 text-slate-500">
                Provider aktif ditentukan oleh tombol yang dipilih dan disimpan. Bila masih ada transaksi aktif, sistem mempertahankan provider lama sementara sampai antrean aman untuk dipindahkan.
            </div>

            <button class="btn-primary">Simpan & gunakan provider terpilih</button>
        </form>
    @else
        <form class="card space-y-5 p-6" method="post" action="{{ route('admin.settings.update') }}" @if($tab==='site') enctype="multipart/form-data" @endif>
            @csrf @method('put')
            <input type="hidden" name="group" value="{{ $tab }}">

            @if($tab==='site')
                <div><label class="label">Nama situs</label><input class="input" name="name" value="{{ $v('name') }}" required></div>
                <div><label class="label">Deskripsi situs</label><textarea class="input min-h-28" name="description" required>{{ $v('description') }}</textarea><p class="mt-1 text-xs text-slate-500">Dipakai sebagai deskripsi umum aplikasi.</p></div>
                <div><label class="label">WhatsApp dukungan</label><input class="input" name="support_whatsapp" value="{{ $v('support_whatsapp') }}" placeholder="62812..."></div>

                <div class="rounded-3xl border border-slate-200 p-5 dark:border-white/10">
                    <h3 class="font-black">Logo bisnis & favicon</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Logo dapat diambil dari URL atau di-upload langsung dari perangkat. Logo aktif dipakai pada header, sidebar, footer, sekaligus favicon browser.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">URL logo bisnis</label>
                            <input class="input" type="url" name="logo_url" value="{{ $logoUrlInput }}" placeholder="https://domain.com/logo.png">
                            <p class="mt-1 text-xs text-slate-500">Boleh dikosongkan jika memakai file lokal yang sudah aktif.</p>
                        </div>
                        <div>
                            <label class="label">Pilih file logo</label>
                            <input class="input" type="file" name="logo_image" accept="image/jpeg,image/png,image/webp">
                            <p class="mt-1 text-xs text-slate-500">JPG/PNG/WebP maksimal 4 MB. Untuk tampil paling cepat di HP, upload file lokal lebih disarankan daripada URL eksternal.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid max-w-2xl gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">Perbesaran logo (%)</label>
                            <input class="input" type="number" name="logo_zoom" min="100" max="400" step="10" value="{{ $v('logo_zoom',240) }}">
                            <p class="mt-1 text-xs text-slate-500">Naikkan jika file logo memiliki ruang transparan besar; turunkan jika logo terpotong.</p>
                        </div>
                        <div>
                            <label class="label">Posisi logo di HP (%)</label>
                            <input class="input" type="number" name="logo_mobile_shift" min="-45" max="45" step="1" value="{{ $v('logo_mobile_shift',-30) }}">
                            <p class="mt-1 text-xs text-slate-500">Nilai negatif menggeser logo ke kiri. Default -30% agar wordmark lebih dekat ke tepi layar.</p>
                        </div>
                    </div>
                    @if(filled($activeLogoUrl))
                        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10">
                            <div class="relative h-20 w-full max-w-xs overflow-hidden rounded-xl bg-slate-50 dark:bg-slate-900/40">
                                <img src="{{ $activeLogoUrl }}" alt="Pratinjau logo" class="absolute inset-0 h-full w-full object-contain" style="transform:scale({{ max(100,min(400,(int)$v('logo_zoom',240))) / 100 }});transform-origin:center;" loading="eager" fetchpriority="high">
                            </div>
                            <div class="mt-2 text-xs font-black">Pratinjau logo aktif</div>
                            <span class="text-xs text-slate-500">Logo yang sama juga digunakan sebagai favicon. Tampilan header/sidebar mengikuti nilai perbesaran di atas.</span>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl border border-slate-200 p-5 dark:border-white/10">
                    <h3 class="font-black">META SEO & thumbnail link</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Metadata ini dipakai pada halaman publik dan Open Graph. Mesin pencari tetap menentukan sendiri bagaimana hasil akhirnya ditampilkan.</p>
                    <div class="mt-4 grid gap-4">
                        <div><label class="label">Judul SEO</label><input class="input" name="seo_title" maxlength="70" value="{{ $v('seo_title') }}" placeholder="Contoh: OTP Virtual Cepat & Aman"></div>
                        <div><label class="label">Teks SEO / deskripsi</label><textarea class="input min-h-24" name="seo_description" maxlength="180" placeholder="Ringkasan singkat untuk mesin pencari dan preview link.">{{ $v('seo_description') }}</textarea></div>
                        <div><label class="label">Kata kunci SEO</label><input class="input" name="seo_keywords" value="{{ $v('seo_keywords') }}" placeholder="otp virtual, sms otp, nomor virtual"></div>
                        <div><label class="label">Hashtag</label><input class="input" name="seo_hashtags" value="{{ $v('seo_hashtags') }}" placeholder="#otp #sms #verifikasi"><p class="mt-1 text-xs text-slate-500">Disimpan sebagai metadata tambahan; tidak menjamin posisi tertentu di hasil pencarian.</p></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label">URL META SEO Image</label>
                                <input class="input" type="url" name="seo_image_url" value="{{ $seoImageUrlInput }}" placeholder="https://domain.com/thumbnail.jpg">
                                <p class="mt-1 text-xs text-slate-500">Gunakan URL publik gambar jika thumbnail disimpan di luar server.</p>
                            </div>
                            <div>
                                <label class="label">Pilih file META SEO Image</label>
                                <input class="input" type="file" name="seo_image" accept="image/jpeg,image/png,image/webp">
                                <p class="mt-1 text-xs text-slate-500">JPG/PNG/WebP maksimal 4 MB. Disarankan 1200×630. Jika URL dan file diisi bersamaan, file lokal yang dipakai.</p>
                            </div>
                        </div>
                        @if(filled($activeSeoImageUrl))
                            <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10"><img src="{{ $activeSeoImageUrl }}" alt="META SEO image aktif" class="aspect-[1.91/1] w-full bg-slate-100 object-cover dark:bg-white/5"><div class="p-3 text-xs font-semibold text-slate-500">Thumbnail META SEO yang sedang aktif, baik dari URL maupun upload lokal.</div></div>
                        @endif
                    </div>
                </div>
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
            @elseif($tab==='mail')
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Pengirim email</label><select class="input" name="mailer"><option value="smtp" @selected($v('mailer','smtp')==='smtp')>SMTP</option><option value="log" @selected($v('mailer')==='log')>Hanya catat log</option></select></div><div><label class="label">Host SMTP</label><input class="input" name="host" value="{{ $v('host') }}" required></div><div><label class="label">Porta</label><input class="input" type="number" name="port" value="{{ $v('port',587) }}" required></div><div><label class="label">Enkripsi</label><select class="input" name="encryption"><option value="" @selected($v('encryption')==='')>Tanpa enkripsi</option><option value="tls" @selected($v('encryption','tls')==='tls')>TLS / STARTTLS</option><option value="ssl" @selected($v('encryption')==='ssl')>SSL</option></select></div><div><label class="label">Nama pengguna</label><input class="input" name="username" value="{{ $v('username') }}"></div><div><label class="label">Kata sandi</label><x-password-input name="password" value="" autocomplete="new-password" placeholder="Kosongkan untuk mempertahankan kata sandi" /></div><div><label class="label">Alamat pengirim</label><input class="input" type="email" name="from_address" value="{{ $v('from_address') }}" required></div><div><label class="label">Nama pengirim</label><input class="input" name="from_name" value="{{ $v('from_name') }}" required></div></div>
            @elseif($tab==='security')
                <div><label class="label">Rahasia webhook SMS Virtual</label><x-password-input name="provider_webhook_secret" value="" placeholder="Minimal 24 karakter; kosongkan untuk mempertahankan" /></div>
            @endif

            <button class="btn-primary">Simpan {{ $tabs[$tab][0] }}</button>
        </form>
    @endif

    <aside class="space-y-4">
        <section class="card p-5">
            <h2 class="font-black">Tes & sinkronisasi</h2>
            <div class="mt-4 grid gap-2">
                @if($tab==='sms_virtual')
                    <form method="post" action="{{ route('admin.settings.test-sms') }}">@csrf<button class="btn-secondary w-full justify-between">Tes saldo SMS Virtual <x-icon name="arrow-right" size="size-4" /></button></form>
                    <form method="post" action="{{ route('admin.settings.sync-catalog') }}">@csrf<button class="btn-secondary w-full justify-between">Sinkronkan katalog <x-icon name="arrow-right" size="size-4" /></button></form>
                @elseif($tab==='payments')
                    <form method="post" action="{{ route('admin.settings.test-pakasir') }}">@csrf<button class="btn-secondary w-full justify-between">Tes Pakasir <x-icon name="arrow-right" size="size-4" /></button></form>
                    <form method="post" action="{{ route('admin.settings.test-duitku') }}">@csrf<button class="btn-secondary w-full justify-between">Tes Duitku <x-icon name="arrow-right" size="size-4" /></button></form>
                @elseif($tab==='mail')
                    <form method="post" action="{{ route('admin.settings.test-mail') }}">@csrf<button class="btn-secondary w-full justify-between">Kirim email uji <x-icon name="arrow-right" size="size-4" /></button></form>
                @else
                    <p class="text-sm leading-6 text-slate-500">Tidak ada pengujian manual khusus untuk kategori ini.</p>
                @endif
            </div>
        </section>
        <section class="card p-5 text-sm leading-6 text-slate-500">
            <div class="flex items-center gap-2"><x-icon name="shield" class="text-emerald-500" /><h2 class="font-black text-slate-800 dark:text-slate-100">Catatan keamanan</h2></div>
            <p class="mt-2">API key, signature, request transaksi, dan cek status penyedia hanya berjalan di backend. Frontend hanya menerima data yang memang dibutuhkan pengguna.</p>
        </section>
    </aside>
</div>
@endif
@endsection
