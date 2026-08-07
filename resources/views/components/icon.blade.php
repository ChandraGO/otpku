@props(['name', 'size' => 'size-5', 'stroke' => 1.8])
<svg {{ $attributes->merge(['class' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'aria-hidden' => 'true']) }} stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('home')<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>@break
        @case('services')<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>@break
        @case('orders')<path d="M7 3h10l2 4v13H5V7l2-4Z"/><path d="M5 8h14"/><path d="M9 12h6M9 16h4"/>@break
        @case('wallet')<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H18v16H6.5A2.5 2.5 0 0 1 4 17.5v-11Z"/><path d="M4 7h14"/><path d="M14 11h7v5h-7a2.5 2.5 0 0 1 0-5Z"/><circle cx="15" cy="13.5" r=".5" fill="currentColor" stroke="none"/>@break
        @case('topup')<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M12 9v6M9 12h6"/>@break
        @case('api')<path d="M8 9 4 12l4 3M16 9l4 3-4 3M14 5l-4 14"/>@break
        @case('ticket')<path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6V7Z"/><path d="M13 8v2M13 14v2"/>@break
        @case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>@break
        @case('users')<path d="M16 21a6 6 0 0 0-12 0"/><circle cx="10" cy="8" r="4"/><path d="M18 11a3 3 0 1 0-2.5-4.7M21 21a5 5 0 0 0-4-4.9"/>@break
        @case('settings')<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>@break
        @case('chart')<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
        @case('database')<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>@break
        @case('announcement')<path d="m3 11 15-5v12L3 13v-2Z"/><path d="M8 15v4a2 2 0 0 0 2 2h1"/><path d="M18 10a2 2 0 0 1 0 4"/>@break
        @case('logout')<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>@break
        @case('menu')<path d="M4 7h16M4 12h16M4 17h16"/>@break
        @case('sun')<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.41M17.66 6.34l1.41-1.41"/>@break
        @case('moon')<path d="M21 12.7A8.5 8.5 0 1 1 11.3 3 6.5 6.5 0 0 0 21 12.7Z"/>@break
        @case('search')<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>@break
        @case('filter')<path d="M4 5h16M7 12h10M10 19h4"/>@break
        @case('chevron-right')<path d="m9 18 6-6-6-6"/>@break
        @case('arrow-right')<path d="M5 12h14M13 6l6 6-6 6"/>@break
        @case('shield')<path d="M12 3 5 6v5c0 4.8 2.8 8.2 7 10 4.2-1.8 7-5.2 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>@break
        @case('bolt')<path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z"/>@break
        @case('globe')<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>@break
        @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>@break
        @case('github')<path d="M12 2.5a9.5 9.5 0 0 0-3 18.5c.48.09.65-.21.65-.46v-1.8c-2.67.58-3.23-1.13-3.23-1.13-.44-1.1-1.07-1.4-1.07-1.4-.87-.6.07-.59.07-.59.96.07 1.47.99 1.47.99.86 1.47 2.25 1.05 2.8.8.09-.62.34-1.05.61-1.29-2.13-.24-4.37-1.06-4.37-4.74 0-1.05.38-1.9.99-2.57-.1-.24-.43-1.22.09-2.54 0 0 .8-.26 2.61.98A9 9 0 0 1 12 7.02a9 9 0 0 1 2.38.32c1.81-1.24 2.61-.98 2.61-.98.52 1.32.19 2.3.09 2.54.62.67.99 1.52.99 2.57 0 3.69-2.25 4.5-4.39 4.74.35.3.65.88.65 1.78v2.64c0 .25.17.56.66.46A9.5 9.5 0 0 0 12 2.5Z" fill="currentColor" stroke="none"/>@break
        @case('eye')<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>@break
        @case('eye-off')<path d="m3 3 18 18"/><path d="M10.6 6.2A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.2 2.9M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6a9 9 0 0 0 3.3-.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>@break
        @case('warning')<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17.5v.5"/>@break
        @case('check')<path d="m5 12 4 4L19 6"/>@break
        @case('copy')<rect x="8" y="8" width="11" height="11" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>@break
        @case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>@break
        @default<circle cx="12" cy="12" r="9"/>@break
    @endswitch
</svg>
