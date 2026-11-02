@props([
    'formats' => ['print', 'csv', 'xls'],
])
@php
    $formats = is_array($formats) ? $formats : ['print', 'csv', 'xls'];
    $q = request()->query();
    unset($q['export']);
    $base = url()->current();
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
    @if (in_array('print', $formats, true))
        <button type="button" onclick="window.print()" class="btn-secondary btn-sm no-print">{{ __('Print') }}</button>
    @endif
    @if (in_array('csv', $formats, true))
        <a href="{{ $base.'?'.http_build_query($q + ['export' => 'csv']) }}"
           class="btn-secondary btn-sm no-print">{{ __('CSV') }}</a>
    @endif
    @if (in_array('xls', $formats, true))
        <a href="{{ $base.'?'.http_build_query($q + ['export' => 'xls']) }}"
           class="btn-secondary btn-sm no-print">{{ __('Excel') }}</a>
    @endif
</div>
