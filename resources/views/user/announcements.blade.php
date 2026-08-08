@extends('layouts.app')
@php($title = 'Pengumuman')
@section('content')
<div><h1 class="text-3xl font-black tracking-tight">Pengumuman</h1></div>
<div class="mt-6 space-y-4">
    @forelse($announcements as $item)
        <article class="card overflow-hidden">
            @if($item->imageUrl())<img src="{{ $item->imageUrl() }}" alt="Gambar {{ $item->title }}" class="aspect-video w-full object-cover">@endif
            <div class="p-5 sm:p-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><div class="flex items-center gap-2"><h2 class="text-lg font-bold">{{ $item->title }}</h2>@if($item->is_pinned)<span title="Disematkan">★</span>@endif</div><p class="mt-1 text-xs text-slate-500">{{ $item->created_at->format('d M Y H:i') }}</p></div><x-status :value="$item->type" /></div>
                <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-400">{{ $item->body }}</p>
            </div>
        </article>
    @empty
        <div class="card p-10 text-center text-slate-500">Belum ada pengumuman.</div>
    @endforelse
</div>
<div class="mt-6">{{ $announcements->links() }}</div>
@endsection
