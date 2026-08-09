@extends('layouts.app')
@php
    $editing = $announcement->exists;
    $title = $editing ? 'Ubah Pengumuman' : 'Tambah Pengumuman';
    $categories = [
        'info' => 'Informasi',
        'important' => 'Penting',
        'news' => 'Berita',
        'update' => 'Update',
        'deposit' => 'Deposit',
        'service' => 'Layanan',
    ];
    $currentType = old('type', $announcement->type ?: 'info');
    if (! array_key_exists($currentType, $categories)) {
        $currentType = in_array($currentType, ['warning', 'danger'], true) ? 'important' : 'info';
    }
@endphp
@section('content')
<div>
    <a class="text-sm font-bold text-violet-600 dark:text-violet-300" href="{{ route('admin.announcements.index') }}">← Pengumuman</a>
    <h1 class="mt-2 text-3xl font-black">{{ $editing ? 'Ubah' : 'Tambah' }} pengumuman</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Gunakan kategori agar informasi di dashboard dan halaman pengumuman lebih mudah dipindai.</p>
</div>
<form class="card mt-6 max-w-3xl space-y-5 p-6" method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
    @csrf
    @if($editing) @method('put') @endif
    <div><label class="label">Judul</label><input class="input" name="title" value="{{ old('title', $announcement->title) }}" required></div>
    <div><label class="label">Isi</label><textarea class="input min-h-52" name="body" required>{{ old('body', $announcement->body) }}</textarea><p class="mt-2 text-xs text-slate-500">Baris baru akan dipertahankan saat ditampilkan.</p></div>
    <div>
        <label class="label">Gambar pengumuman</label>
        <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <p class="mt-2 text-xs text-slate-500">Gunakan rasio 16:9. Rekomendasi 1280×720 px, maksimum 5 MB.</p>
        @if($announcement->imageUrl())
            <img src="{{ $announcement->imageUrl() }}" alt="Gambar pengumuman" class="mt-3 aspect-video w-full rounded-2xl border border-slate-200 object-cover dark:border-white/10">
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Hapus gambar saat ini</label>
        @endif
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label class="label">Kategori</label>
            <select class="input" name="type" required>
                @foreach($categories as $type => $label)
                    <option value="{{ $type }}" @selected($currentType === $type)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Mulai tampil</label><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="label">Berakhir</label><input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[.03]">
        <div class="text-xs font-black uppercase tracking-[.16em] text-slate-400">Pratinjau kategori</div>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach(array_keys($categories) as $type)
                <x-announcement-category :value="$type" />
            @endforeach
        </div>
    </div>
    <div class="flex flex-wrap gap-5">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->exists ? $announcement->is_active : true))> Aktif</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))> Sematkan</label>
    </div>
    <button class="btn-primary">Simpan pengumuman</button>
</form>
@endsection
