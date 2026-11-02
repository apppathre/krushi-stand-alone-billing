{{-- Full-page create redirects to bulk; this file kept only as a fallback. --}}
@php
    header('Location: '.route('opening-stock.bulk'));
    exit;
@endphp
