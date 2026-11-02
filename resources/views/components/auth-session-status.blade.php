@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg bg-sage px-3 py-2 text-sm font-semibold text-leaf-dark']) }}>
        {{ $status }}
    </div>
@endif
