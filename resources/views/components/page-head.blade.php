{{-- Form/content title + Help / Recent on one inline row (not topbar). --}}
@props([
    'title',
    'blurb' => null,
])

<div {{ $attributes->class(['page-head']) }}>
    <div class="page-head__text">
        <h2 class="page-head__title">{{ $title }}</h2>
        @if ($blurb)
            <p class="page-head__blurb">{{ $blurb }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="page-head__actions">
            {{ $slot }}
        </div>
    @endif
</div>
