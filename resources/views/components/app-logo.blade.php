@props(['compact' => false, 'inverse' => false])
<span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center']) }}>
    @if(filled($siteLogoUrl ?? null))
        {{-- Logo bisnis kustom memakai seluruh area brand, bukan lagi dibatasi kotak ikon kecil. --}}
        <img
            src="{{ $siteLogoUrl }}"
            alt="Logo {{ $siteName }}"
            class="block shrink-0 object-contain"
            style="{{ $compact
                ? 'height:40px;width:auto;max-width:min(58vw,180px);object-position:left center;'
                : 'height:56px;width:auto;max-width:240px;object-position:left center;' }}"
            referrerpolicy="no-referrer"
            loading="eager"
        >
    @else
        <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 via-violet-500 to-cyan-400 text-white shadow-lg shadow-violet-500/20">
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
