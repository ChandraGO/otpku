@props(['service', 'size' => 'md'])
@php
    $classes = match ($size) {
        'sm' => 'size-10 rounded-xl text-xs',
        'lg' => 'size-16 rounded-2xl text-lg',
        default => 'size-12 rounded-2xl text-sm',
    };
    $name = trim((string) ($service?->name ?? 'OTP'));
    $initials = collect(preg_split('/\s+/', $name))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $initials = $initials !== '' ? $initials : 'OT';
@endphp
<span {{ $attributes->merge(['class' => $classes.' relative grid shrink-0 place-items-center overflow-hidden border border-slate-200 bg-white font-black text-violet-600 shadow-sm dark:border-white/10 dark:bg-slate-900 dark:text-cyan-300']) }}>
    @if(filled($service?->icon_url))
        <img src="{{ $service->icon_url }}" alt="Logo {{ $name }}" loading="lazy" referrerpolicy="no-referrer" class="size-full object-contain p-1.5" onerror="this.hidden=true;this.nextElementSibling.classList.remove('hidden')">
        <span class="hidden">{{ $initials }}</span>
    @else
        <span>{{ $initials }}</span>
    @endif
</span>
