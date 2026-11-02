<form method="POST"
      action="{{ $item->exists ? route($c['route'].'.update', $item) : route($c['route'].'.store') }}"
      data-ajax-form
      class="space-y-4">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    @foreach ($c['fields'] as $f)
        @php($type = $f['type'] ?? 'text')
        @if ($type === 'checkbox')
            <x-check :name="$f['name']" :label="__($f['label'])" :checked="old($f['name'], $item->{$f['name']})" />
        @elseif ($type === 'select')
            <x-select-field :name="$f['name']" :label="__($f['label'])" :value="old($f['name'], $item->{$f['name']})"
                            :required="$f['required'] ?? false" :options="collect($f['options'] ?? [])->map(fn ($l) => __($l))->all()" />
        @else
            <x-field :name="$f['name']" :label="__($f['label'])" :type="$type" :value="old($f['name'], $item->{$f['name']})" :required="$f['required'] ?? false" :hint="isset($f['hint']) ? __($f['hint']) : null" />
        @endif
    @endforeach

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
