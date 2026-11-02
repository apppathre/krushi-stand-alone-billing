@props(['name' => 'tag'])

@php
    $paths = [
        'home' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
        'cart' => 'M3 4h2l2.4 10h10.2L20 7H6M9 19a1 1 0 100 2 1 1 0 000-2zM17 19a1 1 0 100 2 1 1 0 000-2z',
        'book' => 'M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3zM5 17a3 3 0 013-3h11',
        'cog' => 'M12 9a3 3 0 100 6 3 3 0 000-6zM12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'leaf' => 'M5 19c0-9 5-14 15-14 0 10-5 15-14 15M5 19l8-8',
        'user' => 'M12 4a4 4 0 100 8 4 4 0 000-8zM4 20c0-4 4-6 8-6s8 2 8 6',
        'users' => 'M16 8a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM3.5 20c.8-3.2 3.6-5 7-5M14 13c3.4 0 6.2 1.8 7 5M19 8a2.5 2.5 0 11-2.2-4',
        'tag' => 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01',
        'chart' => 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3',
        'doc' => 'M7 3h8l4 4v14H7zM15 3v4h4M10 12h6M10 16h6',
        'return' => 'M9 14L4 9l5-5M4 9h11a5 5 0 010 10h-3',
        'truck' => 'M2 6h12v10H2zM14 10h4l3 3v3h-7zM6 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'building' => 'M4 21V8l8-5 8 5v13M9 21v-6h6v6',
        'mail' => 'M3 6h18v12H3zM3 7l9 7 9-7',
        'link' => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
        'plus' => 'M12 5v14M5 12h14',
        'x' => 'M6 6l12 12M18 6L6 18',
        'alert' => 'M12 4l9 16H3zM12 10v4M12 17h.01',
        'globe' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
        'masters' => 'M4 6h16M4 12h10M4 18h16M14 10l3 3 5-6',
        'item' => 'M4 7h16v12H4zM8 7V5h8v2M9 12h6M9 16h4',
        'ledger' => 'M6 3h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V5a2 2 0 012-2zM9 8h6M9 12h6',
        'purchase' => 'M3 6h2l2 9h10l2-6H8M9 20a1 1 0 100-2 1 1 0 000 2zM17 20a1 1 0 100-2 1 1 0 000 2zM14 4l2 2-5 5H9V9z',
        'sales' => 'M4 19V5h11l5 5v9zM15 5v5h5M8 13h6M8 16h4',
        'payment' => 'M12 3v18M8 8c0-1.5 1.8-3 4-3s4 1.5 4 3-1.8 2.5-4 3-4 1.5-4 3 1.8 3 4 3 4-1.5 4-3',
        'receipt' => 'M7 3h10v18l-2.5-1.5L12 21l-2.5-1.5L7 21zM10 8h4M10 12h4M10 16h2',
        'report' => 'M5 3h10l4 4v14H5zM15 3v4h4M8 13h8M8 17h5M8 9h3',
        'stock' => 'M4 8l8-4 8 4v8l-8 4-8-4zM4 8l8 4 8-4M12 12v8',
        'price' => 'M12 3v2M12 19v2M5 12H3M21 12h-2M6.3 6.3L4.9 4.9M19.1 19.1l-1.4-1.4M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4M12 8a4 4 0 100 8 4 4 0 000-8z',
        'outstanding' => 'M12 8v5l3 2M12 3a9 9 0 100 18 9 9 0 000-18z',
        'factory' => 'M3 21V10l6 4V10l6 4V7h6v14zM7 21v-3M11 21v-3M15 21v-3M19 21v-3',
        'rupee' => 'M7 5h10M7 9h10M11 5c3 0 5 1.5 5 4s-2 4-5 4H9l6 6M7 13h4',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'wallet' => 'M3 7h15a3 3 0 013 3v7a3 3 0 01-3 3H3V7zM3 7l2.5-3H16l2 3M16 13h4',
        'calendar' => 'M8 3v3M16 3v3M4 9h16M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2zM8 13h3M13 13h3M8 17h3',
        'hash' => 'M5 9h14M5 15h14M10 4L8 20M16 4l-2 16',
        'help' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 16h.01M10.5 9.5a1.5 1.5 0 112.4 1.2c-.5.5-1.4 1-1.4 2.3',
    ];
@endphp


<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $paths[$name] ?? $paths['tag'] }}" />
</svg>
