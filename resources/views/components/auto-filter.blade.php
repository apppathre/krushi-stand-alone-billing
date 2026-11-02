@props(['target' => '#list-results', 'action' => null])

{{-- Search/filter bar; optional $actions (Add) sits with Reset to save a full row. --}}
<form method="GET" action="{{ $action ?? url()->current() }}" data-filter
      x-data="autoFilter('{{ $target }}')"
      @input.debounce.350ms="run()" @change="run()" @submit.prevent="run()"
      {{ $attributes->merge(['class' => 'filter-bar card card-body mb-4']) }}>
    <div class="filter-bar__fields">
        {{ $slot }}
    </div>
    <div class="filter-bar__actions">
        @isset($actions)
            {{ $actions }}
        @endisset
        <button type="button" class="btn-secondary btn-sm" @click="reset()">{{ __('Reset') }}</button>
        <span x-show="loading" x-cloak class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-soft">
            <span class="ui-spinner h-3.5 w-3.5"></span> {{ __('Loading…') }}
        </span>
    </div>
</form>
