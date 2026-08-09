@extends('layouts.app')
@php
    $title = 'Berita & Informasi';
@endphp
@section('content')
<div>
    <div class="text-xs font-black uppercase tracking-[.18em] text-violet-600 dark:text-violet-300">{{ $siteName }}</div>
    <h1 class="mt-2 text-3xl font-black tracking-tight">Berita & Informasi</h1>
    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Pembaruan layanan, operasional, deposit, dan informasi penting dalam satu linimasa.</p>
</div>

<div class="card mt-6 p-5 sm:p-7">
    <div class="relative ml-1 border-l border-slate-200 pl-6 dark:border-white/10 sm:ml-2 sm:pl-8">
        @forelse($announcements as $item)
            @php
                $lineClass = match(strtolower((string) $item->type)) {
                    'important', 'warning', 'danger' => 'bg-rose-500',
                    'news' => 'bg-slate-700 dark:bg-slate-300',
                    'update' => 'bg-violet-500',
                    'deposit' => 'bg-emerald-500',
                    'service' => 'bg-amber-500',
                    default => 'bg-sky-500',
                };
            @endphp
            <article class="relative pb-9 last:pb-0">
                <span class="absolute -left-[1.92rem] top-1.5 size-3 rounded-full border-2 border-white {{ $lineClass }} dark:border-[#0f172a] sm:-left-[2.43rem]"></span>
                <div class="flex flex-wrap items-center gap-2">
                    <x-announcement-category :value="$item->type" />
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400"><x-icon name="history" size="size-3.5" /> {{ $item->created_at->format('d M Y (H:i)') }}</span>
                    @if($item->is_pinned)<span class="text-xs text-amber-500" title="Disematkan">★</span>@endif
                </div>
                <h2 class="mt-2 text-lg font-black">{{ $item->title }}</h2>
                @if($item->imageUrl())<img src="{{ $item->imageUrl() }}" alt="Gambar {{ $item->title }}" class="mt-4 aspect-video w-full max-w-3xl rounded-2xl border border-slate-200 object-cover dark:border-white/10">@endif
                <p class="mt-3 max-w-5xl whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $item->body }}</p>
            </article>
        @empty
            <div class="py-10 text-center text-slate-500">Belum ada pengumuman.</div>
        @endforelse
    </div>
</div>
<div class="mt-6">{{ $announcements->links() }}</div>
@endsection
