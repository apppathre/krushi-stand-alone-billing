{{-- Bulk toggle: which active items appear on the live stock TV board --}}
<form method="POST"
      action="{{ route('products.live-board.sync') }}"
      data-ajax-form
      class="flex h-full min-h-0 flex-col gap-3"
      x-data="liveBoardPicker({
          items: @js($items),
          categories: @js($categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name])->values()->all()),
      })">
    @csrf
    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <p class="text-sm text-ink-soft">
        {{ __('Tick items to show on the live stock screen. Untick to hide. Save once — no need to open each product.') }}
    </p>

    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <label class="label" for="lb-q">{{ __('Search') }}</label>
            <input id="lb-q" type="search" class="input input-sm" x-model.debounce.200ms="q" placeholder="{{ __('Name or code…') }}">
        </div>
        <div>
            <label class="label" for="lb-cat">{{ __('Category') }}</label>
            <select id="lb-cat" class="input input-sm" x-model="categoryId">
                <option value="">{{ __('All') }}</option>
                <template x-for="c in categories" :key="c.id">
                    <option :value="String(c.id)" x-text="c.name"></option>
                </template>
            </select>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <button type="button" class="btn-secondary btn-sm" @click="setFiltered(true)">{{ __('Show filtered') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="setFiltered(false)">{{ __('Hide filtered') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="setAll(true)">{{ __('Show all') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="setAll(false)">{{ __('Hide all') }}</button>
        <span class="ml-auto font-semibold text-ink-soft">
            <span x-text="onCount"></span> / <span x-text="items.length"></span> {{ __('on screen') }}
        </span>
    </div>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-sage">
        <table class="w-full text-sm">
            <thead class="sticky top-0 bg-cream text-left text-xs uppercase tracking-wide text-ink-soft">
                <tr>
                    <th class="w-12 px-3 py-2">{{ __('Show') }}</th>
                    <th class="px-3 py-2">{{ __('Item') }}</th>
                    <th class="px-3 py-2">{{ __('Category') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="it in filtered" :key="it.id">
                    <tr class="border-t border-sage" :class="{ 'bg-sage/40': it.show }">
                        <td class="px-3 py-2 text-center">
                            <input type="checkbox" class="checkbox" :checked="it.show" @change="it.show = $event.target.checked">
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-semibold" x-text="it.name"></span>
                            <span class="block text-xs text-ink-soft" x-text="it.code || '—'"></span>
                        </td>
                        <td class="px-3 py-2 text-ink-soft" x-text="it.category || '—'"></td>
                    </tr>
                </template>
                <tr x-show="filtered.length === 0">
                    <td colspan="3" class="px-3 py-6 text-center text-ink-soft">{{ __('No items match.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Post only checked IDs; server turns others off among active items. --}}
    <template x-for="id in selectedIds" :key="'h'+id">
        <input type="hidden" name="show_ids[]" :value="id">
    </template>
    <input type="hidden" name="sync_all" value="1">

    <div class="flex flex-wrap gap-2 border-t border-sage pt-3">
        <button type="submit" class="btn-primary" data-busy-label="{{ __('Saving…') }}">{{ __('Save live screen items') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>

<script>
    function liveBoardPicker(cfg) {
        return {
            items: (cfg.items || []).map((it) => ({ ...it, show: !!it.show })),
            categories: cfg.categories || [],
            q: '',
            categoryId: '',
            get filtered() {
                const q = this.q.trim().toLowerCase();
                return this.items.filter((it) => {
                    if (this.categoryId && String(it.category_id) !== String(this.categoryId)) return false;
                    if (!q) return true;
                    return (it.name || '').toLowerCase().includes(q)
                        || (it.code || '').toLowerCase().includes(q)
                        || (it.category || '').toLowerCase().includes(q);
                });
            },
            get selectedIds() {
                return this.items.filter((it) => it.show).map((it) => it.id);
            },
            get onCount() {
                return this.selectedIds.length;
            },
            setFiltered(on) {
                this.filtered.forEach((it) => { it.show = !!on; });
            },
            setAll(on) {
                this.items.forEach((it) => { it.show = !!on; });
            },
        };
    }
</script>
