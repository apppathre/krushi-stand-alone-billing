{{-- Shared mPDF body font. Marathi uses bundled Noto Sans Devanagari (Unicode/OTL). --}}
@php
    $printFontFamily = $printFontFamily ?? \App\Support\PrintFonts::cssFamily($printLang ?? 'en');
@endphp
body { font-family: {!! $printFontFamily !!}; }