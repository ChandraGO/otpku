@props(['value'])
@php
    $key = strtolower((string) $value);
    $style = match ($key) {
        'completed', 'success', 'paid', 'active', 'ready' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
        'pending', 'processing', 'provider_pending', 'creating', 'waiting', 'info' => 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
        'expired', 'cancelled', 'failed', 'banned', 'danger' => 'bg-rose-500/10 text-rose-600 dark:text-rose-300',
        'suspended', 'warning', 'refunded' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        default => 'bg-slate-500/10 text-slate-600 dark:text-slate-300',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$style]) }}>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
