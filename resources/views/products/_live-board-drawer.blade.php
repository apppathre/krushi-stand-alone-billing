{{-- Bulk pick: which active items appear on the live stock TV board --}}
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
        {{ __('Search the list on the left and add items to the live screen on the right. Save once — no need to open each product.') }}
    </p>

    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <label class="label" for="lb-q">{{ __('Search') }}</label>
            <input id="lb-q" type="search" class="input input-sm" x-model.debounce.200ms="q" placeholder="{{ __('Name, code or packing…') }}">
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
        <button type="button" class="btn-secondary btn-sm" @click="addFiltered()">{{ __('Add filtered') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="removeFiltered()">{{ __('Remove filtered') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="addAll()">{{ __('Add all') }}</button>
        <button type="button" class="btn-secondary btn-sm" @click="clearAll()">{{ __('Clear screen') }}</button>
        <span class="ml-auto font-semibold text-ink-soft">
            <span x-text="onCount"></span> / <span x-text="items.length"></span> {{ __('on screen') }}
        </span>
    </div>

    <div class="grid min-h-0 flex-1 gap-3 lg:grid-cols-2">
        {{-- Available catalog --}}
        <div class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-sage">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-sage bg-cream px-3 py-2 text-xs font-semibold uppercase tracking-wide text-ink-soft">
                <span>{{ __('Available') }}</span>
                <span x-text="available.length"></span>
            </div>
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-cream/95 text-left text-[11px] uppercase tracking-wide text-ink-soft">
                        <tr>
                            <th class="px-3 py-2">{{ __('Item') }}</th>
                            <th class="px-3 py-2">{{ __('Packing') }}</th>
                            <th class="w-16 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="it in available" :key="'a'+it.id">
                            <tr class="border-t border-sage hover:bg-sage/30">
                                <td class="px-3 py-2">
                                    <span class="font-semibold" x-text="it.name"></span>
                                    <span class="block text-xs text-ink-soft">
                                        <span x-text="it.code || '—'"></span>
                                        <span x-show="it.category" x-text="' · ' + it.category"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-ink-soft" x-text="it.packing || '—'"></td>
                                <td class="px-2 py-2 text-right">
                                    <button type="button" class="btn-primary btn-sm" @click="addOne(it.id)">{{ __('Add') }}</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="available.length === 0">
                            <td colspan="3" class="px-3 py-6 text-center text-ink-soft" x-text="items.length === 0 ? @js(__('No active products.')) : @js(__('No items left to add (or none match search).'))"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- On live screen --}}
        <div class="flex min-h-0 flex-col overflow-hidden rounded-lg border border-leaf/40 bg-leaf/5">
            <div class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-leaf/30 bg-cream px-3 py-2 text-xs font-semibold uppercase tracking-wide text-leaf-dark">
                <span>{{ __('On live screen') }}</span>
                <span x-text="selected.length + (q || categoryId ? (' / ' + onCount) : '')"></span>
            </div>
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-cream/95 text-left text-[11px] uppercase tracking-wide text-ink-soft">
                        <tr>
                            <th class="px-3 py-2">{{ __('Item') }}</th>
                            <th class="px-3 py-2">{{ __('Packing') }}</th>
                            <th class="w-16 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="it in selected" :key="'s'+it.id">
                            <tr class="border-t border-sage hover:bg-white/60">
                                <td class="px-3 py-2">
                                    <span class="font-semibold" x-text="it.name"></span>
                                    <span class="block text-xs text-ink-soft">
                                        <span x-text="it.code || '—'"></span>
                                        <span x-show="it.category" x-text="' · ' + it.category"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-ink-soft" x-text="it.packing || '—'"></td>
                                <td class="px-2 py-2 text-right">
                                    <button type="button" class="btn-secondary btn-sm" @click="removeOne(it.id)">{{ __('Remove') }}</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="selected.length === 0">
                            <td colspan="3" class="px-3 py-6 text-center text-ink-soft" x-text="onCount === 0 ? @js(__('Nothing on the live screen yet — add from the left.')) : @js(__('No on-screen items match search.'))"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Post only selected IDs; server turns others off among active items. --}}
    <template x-for="id in selectedIds" :key="'h'+id">
        <input type="hidden" name="show_ids[]" :value="id">
    </template>
    <input type="hidden" name="sync_all" value="1">

    <div class="flex flex-wrap gap-2 border-t border-sage pt-3">
        <button type="submit" class="btn-primary" data-busy-label="{{ __('Saving…') }}">{{ __('Save live screen items') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
