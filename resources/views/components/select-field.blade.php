@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $current = (string) old($key, $value);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="f-{{ $key }}" class="label">{{ $label }}@if ($required) <span class="text-accent">*</span>@endif</label>
    @endif
    <select id="f-{{ $key }}" name="{{ $name }}" @required($required) {{ $attributes->except('class')->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $optValue === $current)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p>
    @endif
    @error($key)
        <p class="error-text">{{ $message }}</p>
    @enderror
</div>
