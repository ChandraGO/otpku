@props(['value' => 'info'])
@php
    $key = strtolower((string) $value);
    [$label, $classes, $dot] = match ($key) {
        'important', 'warning', 'danger' => ['PENTING', 'bg-rose-500/10 text-rose-600 dark:text-rose-300', 'bg-rose-500'],
        'news' => ['BERITA', 'bg-slate-900/10 text-slate-700 dark:bg-white/10 dark:text-slate-200', 'bg-slate-700 dark:bg-slate-300'],
        'update' => ['UPDATE', 'bg-violet-500/10 text-violet-700 dark:text-violet-300', 'bg-violet-500'],
        'deposit' => ['DEPOSIT', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'bg-emerald-500'],
        'service' => ['LAYANAN', 'bg-amber-500/10 text-amber-700 dark:text-amber-300', 'bg-amber-500'],
        'success' => ['INFORMASI', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300', 'bg-emerald-500'],
        default => ['INFORMASI', 'bg-sky-500/10 text-sky-700 dark:text-sky-300', 'bg-sky-500'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[10px] font-black tracking-wide '.$classes]) }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
</span>
