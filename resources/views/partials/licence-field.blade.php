@props([
    'name',
    'label',
    'value' => null,
    'tillName' => null,
    'tillValue' => null,
])
<div class="space-y-2 rounded-xl border border-sage-dark/60 bg-sage/20 p-3" x-data="{ n: @js(old($name, $value)) }">
    <div class="flex items-end gap-2">
        <div class="min-w-0 flex-1">
            <label class="mb-1 block text-xs font-semibold text-ink-soft" for="f-{{ $name }}">{{ $label }}</label>
            <input id="f-{{ $name }}" name="{{ $name }}" type="text" class="input w-full" x-model="n"
                   value="{{ old($name, $value) }}" maxlength="120" autocomplete="off">
        </div>
        <button type="button" class="btn-secondary btn-sm shrink-0" title="{{ __('Copy') }}"
                @click="if (!n) return; navigator.clipboard.writeText(String(n)).then(() => $store.ui.toast(@js(__('Copied'))))">
            {{ __('Copy') }}
        </button>
    </div>
    @if ($tillName)
        <div>
            <label class="mb-1 block text-xs font-semibold text-ink-soft" for="f-{{ $tillName }}">{{ __('Valid till') }}</label>
            <input id="f-{{ $tillName }}" name="{{ $tillName }}" type="date" class="input w-full"
                   value="{{ old($tillName, $tillValue ? (\Illuminate\Support\Carbon::parse($tillValue)->toDateString()) : '') }}">
        </div>
    @endif
</div>
