@extends('layouts.app')
@php($editing = $announcement->exists)
@php($title = $editing ? 'Ubah Pengumuman' : 'Tambah Pengumuman')
@section('content')
<div>
    <a class="text-sm text-brand-600 dark:text-brand-300" href="{{ route('admin.announcements.index') }}">← Pengumuman</a>
    <h1 class="mt-2 text-3xl font-black">{{ $editing ? 'Ubah' : 'Tambah' }} pengumuman</h1>
</div>
<form class="card mt-6 max-w-3xl space-y-5 p-6" method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
    @csrf
    @if($editing) @method('put') @endif
    <div><label class="label">Judul</label><input class="input" name="title" value="{{ old('title', $announcement->title) }}" required></div>
    <div><label class="label">Isi</label><textarea class="input min-h-48" name="body" required>{{ old('body', $announcement->body) }}</textarea></div>
    <div>
        <label class="label">Gambar pengumuman</label>
        <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <p class="mt-2 text-xs text-slate-500">Gunakan rasio 16:9 seperti banner YouTube. Rekomendasi 1280×720 px, maksimum 5 MB.</p>
        @if($announcement->imageUrl())
            <img src="{{ $announcement->imageUrl() }}" alt="Gambar pengumuman" class="mt-3 aspect-video w-full rounded-2xl border border-slate-200 object-cover dark:border-white/10">
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Hapus gambar saat ini</label>
        @endif
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label">Tipe</label><select class="input" name="type">@foreach(['info' => 'Informasi', 'success' => 'Berhasil', 'warning' => 'Peringatan', 'danger' => 'Bahaya'] as $type => $label)<option value="{{ $type }}" @selected(old('type', $announcement->type ?: 'info') === $type)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="label">Mulai tampil</label><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div><label class="label">Berakhir</label><input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"></div>
    </div>
    <div class="flex flex-wrap gap-5">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $announcement->exists ? $announcement->is_active : true))> Aktif</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))> Sematkan</label>
    </div>
    <button class="btn-primary">Simpan pengumuman</button>
</form>
@endsection
