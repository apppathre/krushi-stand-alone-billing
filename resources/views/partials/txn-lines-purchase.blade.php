{{-- Responsive purchase lines: card stack on phone, dense grid on desktop. --}}
<div class="txn-lines">
    <div class="txn-line-head txn-line__grid txn-line__grid--purchase"
         :class="{
            'txn-line__grid--multi-place': places.length > 1,
            'txn-line__grid--with-rack': Object.keys(racksByPlace || {}).length
         }">
        <span x-show="places.length > 1">{{ __('Place') }}</span>
        <span>{{ __('Product') }}</span>
        <span>{{ __('Batch') }}</span>
        <span>{{ __('Mfg') }}</span>
        <span>{{ __('Expiry') }}</span>
        <span x-show="Object.keys(racksByPlace || {}).length">{{ __('Rack') }}</span>
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
                 :class="{
                    'txn-line__grid--multi-place': places.length > 1,
                    'txn-line__grid--with-rack': Object.keys(racksByPlace || {}).length
                 }">
                <div x-show="places.length > 1" class="txn-line__full lg:contents">
                    <label class="txn-line__label label">{{ __('Place') }}</label>
                    <select :name="places.length > 1 ? `items[${i}][godown_id]` : ''" x-model="line.godown_id" @change="placeChanged(line)" class="input input-sm" :required="places.length > 1">
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
                    <label class="txn-line__label label">{{ __('Batch no') }}</label>
                    <input :name="`items[${i}][batch_no]`" x-model="line.batch_no" class="input input-sm w-full"
                           :required="product(line)?.batch_required" :disabled="product(line) && !product(line).batch_required">
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Mfg') }}</label>
                    <input type="date" :name="`items[${i}][mfg_date]`" x-model="line.mfg_date" class="input input-sm w-full"
                           :disabled="product(line) && !product(line).batch_required">
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Expiry') }}</label>
                    <input type="date" :name="`items[${i}][expiry_date]`" x-model="line.expiry_date" class="input input-sm w-full"
                           :disabled="product(line) && !product(line).batch_required">
                </div>
                <div x-show="Object.keys(racksByPlace || {}).length">
                    <label class="txn-line__label label">{{ __('Rack') }}</label>
                    <select :name="`items[${i}][rack_id]`" x-model="line.rack_id" class="input input-sm w-full">
                        <option value="">{{ __('Later') }}</option>
                        <template x-for="r in racks(line)" :key="r.id">
                            <option :value="r.id" :selected="String(r.id) === String(line.rack_id)" x-text="r.label"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="txn-line__label label">{{ __('Qty') }}</label>
                    <input type="number" inputmode="decimal" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm w-full" required>
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Rate') }}</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm w-full" required>
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('MRP') }}</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" :name="`items[${i}][mrp]`" x-model="line.mrp" class="input input-sm w-full">
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Disc %') }}</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" max="100" :name="`items[${i}][discount_pct]`" x-model="line.discount_pct" class="input input-sm w-full">
                </div>
                <div>
                    <label class="txn-line__label label">
                        {{ __('GST %') }}
                        <span class="txn-line__gst-flag" x-show="line.gst_overridden" x-cloak>{{ __('changed') }}</span>
                    </label>
                    <div class="flex gap-1">
                        <select :name="`items[${i}][gst_rate]`" class="input input-sm w-full"
                                x-model.number="line.gst"
                                @focus="rememberGst(line)"
                                @change="changeGst(line)">
                            <template x-for="r in gstRates" :key="r">
                                <option :value="r" x-text="r"></option>
                            </template>
                            <option :value="line.gst" x-show="gstRates.indexOf(Number(line.gst)) === -1" x-text="line.gst"></option>
                        </select>
                        <button type="button" class="btn-secondary btn-sm shrink-0" x-show="line.gst_overridden" x-cloak
                                @click="resetGst(line)" title="{{ __('Reset to product GST') }}">↺</button>
                    </div>
                </div>
                <div class="col-span-2 flex items-end justify-between gap-2 lg:col-span-1 lg:block lg:text-right">
                    <div>
                        <span class="txn-line__label label lg:hidden">{{ __('Amount') }}</span>
                        <div class="txn-line__amt" x-text="money(calc(line).total)"></div>
                    </div>
                    <button type="button" class="btn-danger btn-sm" @click="remove(i)" title="{{ __('Remove') }}">&times;</button>
                </div>
            </div>
        </div>
    </template>
</div>
