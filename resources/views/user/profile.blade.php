@extends('layouts.app')
@php($title = 'Account Settings')
@section('content')
<div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-slate-950 via-violet-950 to-slate-900 px-6 py-8 text-white shadow-xl sm:px-8">
    <div class="absolute -right-16 -top-20 size-64 rounded-full bg-cyan-400/15 blur-3xl"></div>
    <div class="relative">
        <span class="badge bg-white/10 text-cyan-200">Pengaturan Akun</span>
        <h1 class="mt-4 text-3xl font-black tracking-tight">Account Settings</h1>
        <p class="mt-2 max-w-2xl text-sm leading-7 text-slate-300">Perbarui profil, keamanan, API key, dan preferensi integrasi akun Anda.</p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-6">
        <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><x-icon name="user" /></span><div><h2 class="text-lg font-black">Update Profile</h2><p class="text-xs text-slate-500">Identitas dan preferensi utama akun.</p></div></div>
        <form class="mt-6 grid gap-4 sm:grid-cols-2" method="post" action="{{ route('profile.update') }}">
            @csrf @method('put')
            <div><label class="label">Full Name</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div><label class="label">Username</label><input class="input" name="username" value="{{ old('username', $user->username) }}" required></div>
            <div><label class="label">Email</label><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required><p class="mt-1 text-xs text-slate-500">Perubahan email memerlukan verifikasi ulang.</p></div>
            <div><label class="label">Whatsapp Number</label><input class="input" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}" placeholder="62812..." required></div>
            <div><label class="label">Telegram ID / Username</label><input class="input" name="telegram_id" value="{{ old('telegram_id', $user->telegram_id) }}" placeholder="Contoh: 123456789 atau @username"></div>
            <div><label class="label">Default Country</label><select class="input" name="default_country_id"><option value="">Pilih otomatis</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected((string) old('default_country_id', $user->default_country_id) === (string) $country->id)>{{ $country->name }}{{ $country->iso_code ? ' · '.$country->iso_code : '' }}</option>@endforeach</select></div>
            <div><label class="label">Tema default</label><select class="input" name="theme" data-theme-select><option value="light" @selected(old('theme', $user->theme) === 'light')>Light</option><option value="dark" @selected(old('theme', $user->theme) === 'dark')>Dark</option></select></div>
            <div class="flex items-end"><button class="btn-primary w-full">Simpan Profil</button></div>
        </form>
    </section>

    <section class="card p-6">
        <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-400/10 dark:text-cyan-300"><x-icon name="shield" /></span><div><h2 class="text-lg font-black">Update Password</h2><p class="text-xs text-slate-500">Gunakan password unik dengan kombinasi huruf dan angka.</p></div></div>
        <form class="mt-6 space-y-4" method="post" action="{{ route('profile.password') }}">
            @csrf @method('put')
            <div><label class="label">Current Password</label><x-password-input name="current_password" autocomplete="current-password" placeholder="Masukkan password saat ini" required /></div>
            <div><label class="label">New Password</label><x-password-input name="password" autocomplete="new-password" placeholder="Masukkan password baru" required /></div>
            <div><label class="label">Confirm New Password</label><x-password-input name="password_confirmation" autocomplete="new-password" placeholder="Ulangi password baru" required /></div>
            <button class="btn-primary">Perbarui Password</button>
        </form>
    </section>
</div>

<section class="card mt-6 overflow-hidden">
    <div class="border-b border-slate-200 p-6 dark:border-white/10">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300"><x-icon name="api" /></span><div><h2 class="text-lg font-black">API Key</h2><p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Gunakan key ini pada header <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-white/10">x-api-key</code> untuk API publik. Perlakukan seperti password dan jangan dibagikan atau disimpan di repository.</p></div></div>
            <a href="{{ route('api.docs') }}" class="btn-secondary shrink-0">Buka API Docs <x-icon name="arrow-right" size="size-4" /></a>
        </div>
    </div>
    <div class="grid gap-6 p-6 lg:grid-cols-[1fr_360px]">
        <div>
            @if(session('new_api_key'))
                <div class="mb-4 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-400/20 dark:bg-emerald-400/10" x-data='copyText(@js(session("new_api_key")))'>
                    <div class="text-xs font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Simpan API key baru sekarang</div>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center"><code class="min-w-0 flex-1 break-all rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-800 dark:bg-slate-950 dark:text-slate-100">{{ session('new_api_key') }}</code><button type="button" class="btn-secondary shrink-0" @click="copy"><x-icon name="copy" size="size-4" /><span x-text="copied ? 'Tersalin' : 'Salin'"></span></button></div>
                    <p class="mt-2 text-xs leading-5 text-emerald-700 dark:text-emerald-300">Key lengkap hanya ditampilkan setelah rotasi. Setelah halaman ditutup, tampilan kembali disamarkan.</p>
                </div>
            @endif
            <label class="label">Your API Key</label>
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-white/10 dark:bg-white/[.03]"><code class="min-w-0 flex-1 break-all text-sm font-bold text-slate-700 dark:text-slate-200">{{ $user->apiKeyMasked() ?: 'Belum dibuat' }}</code><span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">Aktif</span></div>
            <p class="mt-3 text-xs leading-6 text-slate-500">Dibuat {{ $user->api_key_created_at?->format('d M Y H:i') ?? '—' }}. Rotasi akan langsung mencabut key saat ini dan integrasi yang masih memakai key lama akan menerima respons 401.</p>
        </div>
        <form class="card-soft p-5" method="post" action="{{ route('profile.api-key.rotate') }}" onsubmit="return confirm('Rotasi API key sekarang? Key lama akan langsung tidak berlaku.');">
            @csrf
            <h3 class="font-black">Rotasi API Key</h3><p class="mt-1 text-xs leading-5 text-slate-500">Konfirmasi dengan password akun.</p>
            <div class="mt-4"><label class="label">Password</label><x-password-input name="password" autocomplete="current-password" required /></div>
            <button class="btn-primary mt-4 w-full">Buat API Key Baru</button>
        </form>
    </div>
</section>

<section class="card mt-6 border-rose-200 p-6 dark:border-rose-400/20">
    <div class="flex items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300"><x-icon name="warning" /></span><div><h2 class="text-lg font-black">Account Data Deletion</h2><p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Kirim permintaan penghapusan akun dan data terkait. Tim admin akan meninjau permintaan sebelum akun dinonaktifkan dan dihapus dari akses login/API aktif.</p></div></div>
    @if($user->deletion_request_status === 'pending')
        <div class="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200"><strong>Menunggu peninjauan admin.</strong> Permintaan dikirim pada {{ $user->deletion_requested_at?->format('d M Y H:i') }}.</div>
    @else
        <form class="mt-6 grid gap-4 lg:grid-cols-2" method="post" action="{{ route('profile.deletion.request') }}" onsubmit="return confirm('Kirim permintaan penghapusan akun?');">
            @csrf
            <div class="lg:col-span-2"><label class="label">Reason</label><textarea class="input min-h-28" name="reason" placeholder="Jelaskan alasan Anda ingin menghapus akun" required>{{ old('reason') }}</textarea></div>
            <div><label class="label">Konfirmasi Password</label><x-password-input name="password" autocomplete="current-password" required /></div>
            <div class="flex items-end"><button class="btn-danger w-full">Kirim Permintaan Penghapusan</button></div>
        </form>
    @endif
    <p class="mt-4 text-xs leading-6 text-slate-500">Persetujuan akan menonaktifkan akun, mencabut akses API, dan menghapus sesi login. Catatan operasional dapat tetap disimpan bila diperlukan untuk keamanan, pencegahan fraud, akuntansi, penyelesaian sengketa, atau kewajiban hukum.</p>
</section>
@endsection
