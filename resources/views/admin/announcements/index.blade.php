@extends('layouts.app')
@php($title = 'Kelola Pengumuman')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><h1 class="text-3xl font-black">Kelola pengumuman</h1><a class="btn-primary" href="{{ route('admin.announcements.create') }}">Tambah pengumuman</a></div>
<div class="mt-6 space-y-4">
    @forelse($announcements as $item)
        <article class="card overflow-hidden">
            @if($item->imageUrl())<img src="{{ $item->imageUrl() }}" alt="Gambar {{ $item->title }}" class="aspect-video max-h-72 w-full object-cover">@endif
            <div class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-start">
                <div><div class="flex items-center gap-2"><h2 class="font-bold">{{ $item->title }}</h2><x-status :value="$item->type" /></div><p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $item->body }}</p><p class="mt-2 text-xs text-slate-500">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }} · {{ $item->is_pinned ? 'Disematkan' : 'Tidak disematkan' }}</p></div>
                <div class="flex gap-2"><a class="btn-secondary" href="{{ route('admin.announcements.edit', $item) }}">Ubah</a><form method="post" action="{{ route('admin.announcements.destroy', $item) }}" onsubmit="return confirm('Hapus pengumuman?')">@csrf @method('delete')<button class="btn-danger">Hapus</button></form></div>
            </div>
        </article>
    @empty
        <div class="card p-10 text-center text-slate-500">Belum ada pengumuman.</div>
    @endforelse
</div>
<div class="mt-6">{{ $announcements->links() }}</div>
@endsection
