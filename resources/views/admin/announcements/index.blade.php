@extends('layouts.app')
@php($title = 'Kelola Pengumuman')
@section('content')
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <div class="text-xs font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-300">Berita & Informasi</div>
        <h1 class="mt-2 text-3xl font-black">Kelola pengumuman</h1>
        <p class="mt-2 text-sm text-slate-500">Kategori membuat pengumuman lebih mudah dikenali di dashboard pengguna.</p>
    </div>
    <a class="btn-primary" href="{{ route('admin.announcements.create') }}">Tambah pengumuman</a>
</div>
<div class="card mt-6 overflow-hidden">
    <div class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse($announcements as $item)
            <article class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-start sm:p-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-announcement-category :value="$item->type" />
                        <span class="text-xs font-semibold text-slate-400">{{ $item->created_at->format('d M Y · H:i') }}</span>
                        @if($item->is_pinned)<span class="text-xs font-black text-amber-500">★ Disematkan</span>@endif
                    </div>
                    <h2 class="mt-3 text-lg font-black">{{ $item->title }}</h2>
                    <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm leading-6 text-slate-500">{{ $item->body }}</p>
                    <div class="mt-3 text-xs font-semibold {{ $item->is_active ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-400' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</div>
                </div>
                <div class="flex gap-2 sm:justify-end">
                    <a class="btn-secondary" href="{{ route('admin.announcements.edit', $item) }}">Ubah</a>
                    <form method="post" action="{{ route('admin.announcements.destroy', $item) }}" onsubmit="return confirm('Hapus pengumuman?')">@csrf @method('delete')<button class="btn-danger">Hapus</button></form>
                </div>
            </article>
        @empty
            <div class="p-10 text-center text-slate-500">Belum ada pengumuman.</div>
        @endforelse
    </div>
</div>
<div class="mt-6">{{ $announcements->links() }}</div>
@endsection
