@props(['name', 'label', 'checked' => false, 'value' => '1'])

<label {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm font-semibold text-ink']) }}>
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" class="checkbox" @checked(old($name, $checked))>
    <span>{{ $label }}</span>
</label>
