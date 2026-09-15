@props(['name'])

<svg {{ $attributes->merge(['class' => 'shrink-0']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 13h6V4H4v9Zm10 7h6v-7h-6v7ZM4 20h6v-3H4v3Zm10-11h6V4h-6v5Z" />
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="1.5" />
            <path stroke-linecap="round" d="M8 3v4m8-4v4M4 10h16m-11 4h2m2 0h2" />
            @break
        @case('scan')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3m8 0h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M8 12h8M8 15h8M8 9h8" />
            @break
        @case('clipboard')
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6m-7 3h8m-9 4h10m-10 4h6M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
            @break
        @case('rotate')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 9a8 8 0 0 1 13.7-3.7L20 7m0 0V3m0 4h-4M20 15a8 8 0 0 1-13.7 3.7L4 17m0 0v4m0-4h4" />
            @break
        @case('warning')
            <path stroke-linecap="round" stroke-linejoin="round" d="m12 4 8 15H4L12 4Zm0 5v4m0 3h.01" />
            @break
        @case('file')
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm7 0v5h4M8 13h8m-8 4h6" />
            @break
        @case('book')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v17H7.5A2.5 2.5 0 0 0 5 21.5v-17Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 4.5A2.5 2.5 0 0 1 7.5 7H19M5 21.5A2.5 2.5 0 0 1 7.5 19H19" />
            @break
        @case('boxes')
            <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10M8 9l8-4" />
            @break
        @case('shield-check')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.7 2.9 8.3 7 10 4.1-1.7 7-5.3 7-10V6l-7-3Zm-3 9 2 2 4-4" />
            @break
        @case('trend')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 17 9 12l3 3 7-8m0 0h-5m5 0v5" />
            @break
        @case('upload')
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
            @break
        @case('receipt')
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h4" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="8.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12 2.2 2.2 4.8-5" />
            @break
        @case('chart')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 20V10m7 10V4m7 16v-7" />
            <path stroke-linecap="round" d="M3 20h18" />
            @break
        @case('building')
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4l7-2 7 2v17M3 21h18M8 7h1m3 0h1m3 0h1M8 11h1m3 0h1m3 0h1M8 15h1m3 0h1m3 0h1M10 21v-3h4v3" />
            @break
        @case('doctor')
            <circle cx="12" cy="7" r="3" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 21a7 7 0 0 1 14 0M16 12v3m-1.5-1.5h3" />
            @break
        @case('currency')
            <circle cx="12" cy="12" r="8.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 8.5h-3a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-3M12 6.5v11" />
            @break
        @case('users')
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1m6-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6-5a2.5 2.5 0 0 1 0 5m2 9v-1a4 4 0 0 0-3-3.9" />
            @break
        @case('menu')
            <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('chevron-left')
            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
            @break
        @case('chevron-right')
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
            @break
        @case('logout')
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3m-2-4h8m0 0-3-3m3 3-3 3" />
            @break
        @case('close')
            <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
            @break
        @default
            <circle cx="12" cy="12" r="8.5" />
    @endswitch
</svg>
