@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'dotted' => null])

@php
    // dotted lets array-style names (items.0.qty) read their errors/old values correctly.
    $key = $dotted ?? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $current = $type === 'password' ? null : old($key, $value);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="f-{{ $key }}" class="label">{{ $label }}@if ($required) <span class="text-accent">*</span>@endif</label>
    @endif
    <input id="f-{{ $key }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" @required($required)
           {{ $attributes->except('class')->merge(['class' => 'input']) }}>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>
    @endif
    @error($key)
        <p class="error-text">{{ $message }}</p>
    @enderror
</div>
