{{-- Purchase order lines (no batch / stock yet). --}}
<div class="txn-lines">
    <div class="txn-line-head txn-line__grid txn-line__grid--purchase"
         :class="{ 'txn-line__grid--multi-place': places.length > 1 }">
        <span x-show="places.length > 1">{{ __('Place') }}</span>
        <span>{{ __('Product') }}</span>
        <span>{{ __('Qty') }}</span>
        <span>{{ __('Rate') }}</span>
        <span>{{ __('MRP') }}</span>
        <span>{{ __('Disc %') }}</span>
        <span>{{ __('GST %') }}</span>
        <span class="text-right">{{ __('Amount') }}</span>
        <span></span>
    </div>

    <template x-for="(line, i) in lines" :key="line.key">
        <div class="txn-line" data-line>
            <div class="txn-line__grid txn-line__grid--purchase"
                 :class="{ 'txn-line__grid--multi-place': places.length > 1 }">
                <div x-show="places.length > 1" class="txn-line__full lg:contents">
                    <label class="txn-line__label label">{{ __('Place') }}</label>
                    <select :name="places.length > 1 ? `items[${i}][godown_id]` : ''" x-model="line.godown_id" class="input input-sm" :required="places.length > 1">
                        <template x-for="g in places" :key="g.id">
                            <option :value="g.id" :selected="String(g.id) === String(line.godown_id)" x-text="g.name"></option>
                        </template>
                    </select>
                </div>
                <input type="hidden" :name="places.length <= 1 ? `items[${i}][godown_id]` : ''" :value="line.godown_id">

                <div class="txn-line__full">
                    <label class="txn-line__label label">{{ __('Product') }}</label>
                    <select :name="`items[${i}][product_id]`"
                            class="input input-sm js-txn-product"
                            required
                            x-init="$nextTick(() => mountProductSelect($el, line))">
                    </select>
                </div>

                <div>
                    <label class="txn-line__label label">{{ __('Qty') }}</label>
                    <input type="number" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm" required>
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Rate') }}</label>
                    <input type="number" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm" required>
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('MRP') }}</label>
                    <input type="number" step="0.01" min="0" :name="`items[${i}][mrp]`" x-model="line.mrp" class="input input-sm">
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Disc %') }}</label>
                    <input type="number" step="0.01" min="0" max="100" :name="`items[${i}][discount_pct]`" x-model="line.discount_pct" class="input input-sm">
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('GST %') }}</label>
                    <select :name="`items[${i}][gst_rate]`" x-model="line.gst" class="input input-sm">
                        <template x-for="r in gstRates" :key="r">
                            <option :value="r" :selected="Number(line.gst) === Number(r)" x-text="r"></option>
                        </template>
                    </select>
                </div>
                <div class="text-right">
                    <label class="txn-line__label label">{{ __('Amount') }}</label>
                    <p class="input input-sm bg-transparent border-0 font-semibold tabular-nums" x-text="money(calc(line).total)"></p>
                </div>
                <div class="flex items-end">
                    <button type="button" class="btn-danger btn-sm" @click="remove(i)" title="{{ __('Remove') }}">&times;</button>
                </div>
            </div>
        </div>
    </template>
</div>
