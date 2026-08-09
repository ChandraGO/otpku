@props(['value' => 'info'])
@php
    $key = strtolower((string) $value);
    [$label, $classes] = match ($key) {
        'important', 'warning', 'danger' => ['PENTING', 'bg-rose-500/10 text-rose-600 ring-1 ring-inset ring-rose-500/20 dark:text-rose-300'],
        'news' => ['BERITA', 'bg-slate-900/10 text-slate-700 ring-1 ring-inset ring-slate-500/20 dark:bg-white/10 dark:text-slate-200'],
        'update' => ['UPDATE', 'bg-violet-500/10 text-violet-700 ring-1 ring-inset ring-violet-500/20 dark:text-violet-300'],
        'deposit' => ['DEPOSIT', 'bg-emerald-500/10 text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:text-emerald-300'],
        'service' => ['LAYANAN', 'bg-amber-500/10 text-amber-700 ring-1 ring-inset ring-amber-500/20 dark:text-amber-300'],
        'success' => ['INFORMASI', 'bg-emerald-500/10 text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:text-emerald-300'],
        default => ['INFORMASI', 'bg-sky-500/10 text-sky-700 ring-1 ring-inset ring-sky-500/20 dark:text-sky-300'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-lg px-2.5 py-1 text-[10px] font-black tracking-wide '.$classes]) }}>{{ $label }}</span>
