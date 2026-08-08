@props(['compact' => false, 'inverse' => false, 'variant' => 'default'])
@php
    $isSidebar = $variant === 'sidebar';
    $wrapperClass = $isSidebar
        ? 'flex w-full min-w-0 items-center'
        : 'inline-flex min-w-0 items-center';
@endphp
<span {{ $attributes->merge(['class' => $wrapperClass]) }}>
    @if(filled($siteLogoUrl ?? null))
        {{-- Logo bisnis kustom memakai area brand penuh. Khusus sidebar dibuat jauh lebih besar agar wordmark terbaca. --}}
        <img
            src="{{ $siteLogoUrl }}"
            alt="Logo {{ $siteName }}"
            class="block object-contain"
            style="{{ $isSidebar
                ? 'display:block;width:100%;height:96px;max-width:248px;object-fit:contain;object-position:left center;'
                : ($compact
                    ? 'height:42px;width:auto;max-width:min(62vw,190px);object-position:left center;'
                    : 'height:64px;width:auto;max-width:280px;object-position:left center;') }}"
            referrerpolicy="no-referrer"
            loading="eager"
        >
    @else
        <span class="relative grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-violet-500 to-cyan-400 text-white shadow-lg shadow-violet-500/20">
            <svg class="size-7" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                <rect x="7" y="4.5" width="18" height="23" rx="4" stroke="currentColor" stroke-width="2.2"/>
                <path d="M11 10.5h10M11 15h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M19.5 19.5 24 17v7l-4.5-2.5v-2Z" fill="currentColor"/>
                <circle cx="16" cy="24" r="1.2" fill="currentColor"/>
            </svg>
            <span class="absolute -right-1 -top-1 size-3 rounded-full border-2 border-white bg-emerald-400"></span>
        </span>

        @unless($compact)
            <span class="ml-3 leading-none">
                <span class="block text-[1.08rem] font-black tracking-tight {{ $inverse ? 'text-white' : 'text-slate-950 dark:text-white' }}">{{ $siteName }}</span>
                <span class="mt-1 block text-[10px] font-semibold uppercase tracking-[.2em] text-violet-500 dark:text-cyan-300">Virtual OTP Platform</span>
            </span>
        @endunless
    @endif
</span>
