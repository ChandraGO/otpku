@extends('layouts.app')
@php($title = 'Pengaturan Akun')
@section('content')
<div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-slate-950 via-violet-950 to-slate-900 px-6 py-8 text-white shadow-xl sm:px-8">
    <div class="absolute -right-16 -top-20 size-64 rounded-full bg-cyan-400/15 blur-3xl"></div>
    <div class="relative">
        <h1 class="text-3xl font-black tracking-tight">Pengaturan akun</h1>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-6">
        <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="user" /></span><h2 class="text-lg font-black">Perbarui profil</h2></div>
        <form class="mt-6 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('profile.update') }}">
            @csrf @method('put')
            <div><label class="label">Nama lengkap</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div><label class="label">Nama pengguna</label><input class="input" name="username" value="{{ old('username', $user->username) }}" required></div>
            <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
            <div><label class="label">Nomor WhatsApp</label><input class="input" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}" placeholder="62812..." required></div>
            <div><label class="label">ID / nama pengguna Telegram</label><input class="input" name="telegram_id" value="{{ old('telegram_id', $user->telegram_id) }}" placeholder="Contoh: 123456789 atau @username"></div>
            <div><label class="label">Negara bawaan</label><select class="input" name="default_country_id"><option value="">Pilih otomatis</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected((string) old('default_country_id', $user->default_country_id) === (string) $country->id)>{{ $country->name }}{{ $country->iso_code ? ' · '.$country->iso_code : '' }}</option>@endforeach</select></div>
            <div><label class="label">Tema bawaan</label><select class="input" name="theme" data-theme-select><option value="light" @selected(old('theme', $user->theme) === 'light')>Terang</option><option value="dark" @selected(old('theme', $user->theme) === 'dark')>Gelap</option></select></div>
            <div class="flex items-end"><button class="btn-primary w-full">Simpan profil</button></div>
        </form>
    </section>

    <section class="card p-6">
        <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="shield" /></span><h2 class="text-lg font-black">Perbarui kata sandi</h2></div>
        <form class="mt-6 space-y-4" method="post" action="{{ route('profile.password') }}">
            @csrf @method('put')
            <div><label class="label">Kata sandi saat ini</label><x-password-input name="current_password" autocomplete="current-password" placeholder="Masukkan kata sandi saat ini" required /></div>
            <div><label class="label">Kata sandi baru</label><x-password-input name="password" autocomplete="new-password" placeholder="Masukkan kata sandi baru" required /></div>
            <div><label class="label">Konfirmasi kata sandi baru</label><x-password-input name="password_confirmation" autocomplete="new-password" placeholder="Ulangi kata sandi baru" required /></div>
            <button class="btn-primary">Perbarui kata sandi</button>
        </form>
    </section>
</div>

<section class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-200 p-6 dark:border-white/10">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300"><x-icon name="api" /></span><h2 class="text-lg font-black">API Key</h2></div>
            <a href="{{ route('api.docs') }}" class="btn-secondary shrink-0">Buka dokumentasi API <x-icon name="arrow-right" size="size-4" /></a>
        </div>
    </div>
    <div class="grid gap-6 p-6 lg:grid-cols-[1fr_360px]">
        <div>
            <p class="mb-4 text-sm leading-6 text-slate-500">Gunakan key ini pada header <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-white/10">x-api-key</code>. Perlakukan seperti kata sandi dan jangan dibagikan atau disimpan di repositori publik.</p>
            <label class="label">API Key Anda</label>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[.03]" x-data='copyText(@js((string) $user->api_key))'>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <code class="min-w-0 flex-1 break-all text-sm font-bold text-slate-700 dark:text-slate-200">{{ $user->api_key ?: 'Belum dibuat' }}</code>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Aktif</span>
                        <button type="button" class="btn-secondary !px-3 !py-2" @click="copy" @disabled(!filled($user->api_key))><x-icon name="copy" size="size-4" /><span x-text="copied ? 'Tersalin' : 'Salin'"></span></button>
                    </div>
                </div>
            </div>
            <p class="mt-3 text-xs leading-6 text-slate-500">Dibuat {{ $user->api_key_created_at?->format('d M Y H:i') ?? '—' }}. Rotasi langsung mencabut key lama sehingga integrasi yang masih memakainya akan menerima respons 401.</p>
        </div>
        <form class="card-soft p-5" method="post" action="{{ route('profile.api-key.rotate') }}" onsubmit="return confirm('Rotasi API key sekarang? Key lama akan langsung tidak berlaku.');">
            @csrf
            <h3 class="font-black">Rotasi API Key</h3>
            <div class="mt-4"><label class="label">Kata sandi</label><x-password-input name="password" autocomplete="current-password" required /></div>
            <button class="btn-primary mt-4 w-full">Buat API Key baru</button>
        </form>
    </div>
</section>

<section class="card mt-6 border-rose-200 p-6 dark:border-rose-400/20">
    <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300"><x-icon name="warning" /></span><h2 class="text-lg font-black">Penghapusan data akun</h2></div>
    @if($user->deletion_request_status === 'pending')
        <div class="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200"><strong>Menunggu peninjauan admin.</strong> Permintaan dikirim pada {{ $user->deletion_requested_at?->format('d M Y H:i') }}.</div>
    @else
        <form class="mt-6 grid gap-4 lg:grid-cols-2" method="post" action="{{ route('profile.deletion.request') }}" onsubmit="return confirm('Kirim permintaan penghapusan akun?');">
            @csrf
            <div class="lg:col-span-2"><label class="label">Alasan</label><textarea class="input min-h-28" name="reason" placeholder="Jelaskan alasan Anda ingin menghapus akun" required>{{ old('reason') }}</textarea></div>
            <div><label class="label">Konfirmasi kata sandi</label><x-password-input name="password" autocomplete="current-password" required /></div>
            <div class="flex items-end"><button class="btn-danger w-full">Kirim permintaan penghapusan</button></div>
        </form>
    @endif
    <p class="mt-4 text-xs leading-6 text-slate-500">Persetujuan akan menonaktifkan akun, mencabut akses API, dan menghapus sesi login. Catatan operasional dapat tetap disimpan bila diperlukan untuk keamanan, pencegahan penipuan, akuntansi, penyelesaian sengketa, atau kewajiban hukum.</p>
</section>
@endsection
