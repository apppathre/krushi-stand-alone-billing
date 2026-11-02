<form method="POST"
      action="{{ route('opening-stock.store') }}"
      data-ajax-form
      class="space-y-4"
      x-data="openingDrawer({
          products: @js($products),
          placeId: '{{ old('godown_id', $placeId) }}',
      })">
    @csrf

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout" role="note">
        <p class="form-callout__title">{{ __('Add one item') }}</p>
        <p class="mb-0">{{ __('Posts opening qty into the selected place. Use Bulk update for many items at once.') }}</p>
    </div>

    <div class="grid gap-4">
        <div>
            <label class="label" for="f-godown_id">{{ __('Place / Godown') }} <span class="text-accent">*</span></label>
            <select id="f-godown_id" name="godown_id" class="input" required x-model="placeId">
                @foreach ($places as $g)
                    <option value="{{ $g->id }}">{{ $g->display_name }}</option>
                @endforeach
            </select>
        </div>

        <x-field name="movement_date" type="date" :label="__('As on date')"
                 :value="old('movement_date', $voucherDate ?? now()->toDateString())"
                 :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                 :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()"
                 required />

        <div>
            <label class="label" for="f-product_id">{{ __('Product') }} <span class="text-accent">*</span></label>
            <select id="f-product_id" name="items[0][product_id]" class="input" required x-model="productId" @change="pick()">
                <option value="">{{ __('Select product') }}</option>
                <template x-for="p in products" :key="p.id">
                    <option :value="p.id" x-text="(p.code ? p.code + ' · ' : '') + p.name"></option>
                </template>
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2" x-show="productId" x-cloak>
            <div>
                <label class="label" for="f-batch_no">{{ __('Batch no') }} <span class="text-accent" x-show="batchRequired">*</span></label>
                <input id="f-batch_no" type="text" name="items[0][batch_no]" class="input" x-model="batchNo"
                       :required="batchRequired" :disabled="!batchRequired" :placeholder="batchRequired ? '{{ __('Required') }}' : 'NA'">
            </div>
            <div>
                <label class="label" for="f-qty">{{ __('Qty') }} <span class="text-accent">*</span></label>
                <input id="f-qty" type="number" step="0.001" min="0.001" name="items[0][qty]" class="input" x-model="qty" required>
            </div>
            <div>
                <label class="label" for="f-mfg">{{ __('Mfg date') }}</label>
                <input id="f-mfg" type="date" name="items[0][mfg_date]" class="input" x-model="mfgDate" :disabled="!batchRequired">
            </div>
            <div>
                <label class="label" for="f-exp">{{ __('Expiry') }}</label>
                <input id="f-exp" type="date" name="items[0][expiry_date]" class="input" x-model="expiryDate" :disabled="!batchRequired">
            </div>
            <div>
                <label class="label" for="f-rate">{{ __('Cost rate') }} <span class="text-accent">*</span></label>
                <input id="f-rate" type="number" step="0.01" min="0" name="items[0][rate]" class="input" x-model="rate" required>
            </div>
            <div>
                <label class="label" for="f-mrp">{{ __('MRP') }}</label>
                <input id="f-mrp" type="number" step="0.01" min="0" name="items[0][mrp]" class="input" x-model="mrp">
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
