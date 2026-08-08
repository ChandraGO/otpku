@props(['compact' => false, 'inverse' => false, 'variant' => 'default'])
@php
    $isSidebar = $variant === 'sidebar';
    $zoomPercent = max(100, min(400, (int) ($siteLogoZoom ?? 240)));
    $zoomScale = $zoomPercent / 100;

    $wrapperClass = $isSidebar
        ? 'flex w-full min-w-0 items-center'
        : 'inline-flex min-w-0 items-center';

    $logoFrameStyle = $isSidebar
        ? 'width:100%;max-width:252px;height:104px;'
        : ($compact
            ? 'width:min(58vw,210px);height:58px;'
            : 'width:min(72vw,300px);height:72px;');
@endphp
<span {{ $attributes->merge(['class' => $wrapperClass]) }}>
    @if(filled($siteLogoUrl ?? null))
        {{--
            Banyak logo hasil ekspor memiliki canvas/ruang transparan yang lebar.
            Frame + zoom membuat wordmark tetap terbaca besar tanpa mengubah file asli.
            Nilai zoom dapat diatur dari Admin > Situs & SEO.
        --}}
        <span
            class="relative block min-w-0 overflow-hidden"
            style="{{ $logoFrameStyle }}"
            aria-hidden="true"
        >
            <img
                src="{{ $siteLogoUrl }}"
                alt=""
                width="300"
                height="104"
                class="absolute inset-0 block h-full w-full object-contain"
                style="object-position:center center;transform:scale({{ number_format($zoomScale, 2, '.', '') }});transform-origin:center center;"
                referrerpolicy="no-referrer"
                loading="eager"
                fetchpriority="high"
                decoding="async"
            >
        </span>
        <span class="sr-only">Logo {{ $siteName }}</span>
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
