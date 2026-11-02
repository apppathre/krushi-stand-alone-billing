<form method="POST" action="{{ route('menus.update', $menu) }}" data-ajax-form class="space-y-4">
    @csrf @method('PUT')

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <x-field name="label_en" :label="__('Label (English)')" :value="$menu->label_en" required />
    <x-field name="label_mr" :label="__('Label (Marathi)')" :value="$menu->label_mr" />
    <x-field name="icon" :label="__('Icon')" :value="$menu->icon" :hint="__('home, box, cart, book, cog, shield, leaf, user, tag, chart, doc, return, truck, menu, building, mail, link')" />
    <x-field name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$menu->sort_order" required />
    @if ($protected)
        <p class="text-xs font-semibold text-accent">{{ __('This menu is required for administration and cannot be hidden.') }}</p>
    @else
        <x-check name="is_active" :label="__('Active (visible in sidebar)')" :checked="$menu->is_active" />
    @endif

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
