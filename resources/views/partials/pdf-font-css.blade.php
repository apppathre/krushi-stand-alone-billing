{{-- Shared DomPDF body font. Marathi uses bundled Noto Sans Devanagari. --}}
@php
    $printFontFamily = $printFontFamily ?? \App\Support\PrintFonts::cssFamily($printLang ?? 'en');
@endphp
body { font-family: {!! $printFontFamily !!}; }