@extends('layouts.app')
@php
    $title = 'Berita & Informasi';
@endphp
@section('content')
<div>
    <div class="text-xs font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-300">{{ $siteName }}</div>
    <h1 class="mt-2 text-3xl font-black tracking-tight">Berita & Informasi</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Pembaruan layanan, operasional, deposit, dan informasi penting dalam satu tempat.</p>
</div>

<div class="card mt-6 p-4 sm:p-7">
    <div class="space-y-4">
        @forelse($announcements as $item)
            @php
                $accentClass = match(strtolower((string) $item->type)) {
                    'important', 'warning', 'danger' => 'border-rose-500',
                    'news' => 'border-slate-500',
                    'update' => 'border-violet-500',
                    'deposit' => 'border-emerald-500',
                    'service' => 'border-amber-500',
                    default => 'border-sky-500',
                };
            @endphp
            <article class="rounded-2xl border border-slate-200 border-l-4 {{ $accentClass }} bg-white p-4 sm:p-5 dark:border-y-white/10 dark:border-r-white/10 dark:bg-white/[.025]">
                <div class="flex flex-wrap items-center gap-2">
                    <x-announcement-category :value="$item->type" />
                    @if($item->is_pinned)
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-400/35 bg-amber-400/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-600 dark:text-amber-300" title="Pengumuman disematkan">📌 Disematkan</span>
                    @endif
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400"><x-icon name="history" size="size-3.5" /> {{ $item->created_at->format('d M Y (H:i)') }}</span>
                </div>
                <h2 class="mt-3 text-lg font-black">{{ $item->title }}</h2>
                @if($item->imageUrl())
                    <img src="{{ $item->imageUrl() }}" alt="Gambar {{ $item->title }}" class="mt-4 block h-auto max-h-[42rem] w-auto max-w-full rounded-[1.35rem] border border-slate-200 object-contain shadow-sm dark:border-white/10" loading="lazy" decoding="async">
                @endif
                <p class="mt-3 max-w-5xl whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $item->body }}</p>
            </article>
        @empty
            <div class="py-10 text-center text-slate-500">Belum ada pengumuman.</div>
        @endforelse
    </div>
</div>
<div class="mt-6">{{ $announcements->links() }}</div>
@endsection
