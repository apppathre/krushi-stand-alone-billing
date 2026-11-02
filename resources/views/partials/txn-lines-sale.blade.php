{{-- Responsive sale lines: card stack on phone, dense grid on desktop. Single set of inputs. --}}
<div class="txn-lines">
    <div class="txn-line-head txn-line__grid txn-line__grid--sale"
         :class="places.length > 1 ? 'txn-line__grid--multi-place' : ''">
        <span x-show="places.length > 1">{{ __('Place') }}</span>
        <span>{{ __('Product') }}</span>
        <span>{{ __('Batch') }}</span>
        <span>{{ __('Qty') }}</span>
        <span>{{ __('Rate') }}</span>
        <span>{{ __('Disc %') }}</span>
        <span>{{ __('GST %') }}</span>
        <span class="text-right">{{ __('Amount') }}</span>
        <span></span>
    </div>

    <template x-for="(line, i) in lines" :key="line.key">
        <div class="txn-line" data-line>
            <div class="txn-line__grid txn-line__grid--sale"
                 :class="places.length > 1 ? 'txn-line__grid--multi-place' : ''">
                {{-- Place only when staff can sell from more than one godown; invoice print uses DC for locations. --}}
                <div x-show="places.length > 1" class="txn-line__full lg:contents">
                    <label class="txn-line__label label">{{ __('From place') }}</label>
                    <select :name="places.length > 1 ? `items[${i}][godown_id]` : ''" x-model="line.godown_id" @change="placeChanged(line)" class="input input-sm" :required="places.length > 1">
                        <template x-for="g in places" :key="g.id">
                            <option :value="g.id" :selected="String(g.id) === String(line.godown_id)"
                                    x-text="g.name + (stockPlaces(line).find(s => String(s.godown_id) === String(g.id)) ? ' (' + stockPlaces(line).find(s => String(s.godown_id) === String(g.id)).qty + ')' : '')"></option>
                        </template>
                    </select>
                </div>
                <input type="hidden" :name="places.length <= 1 ? `items[${i}][godown_id]` : ''" :value="line.godown_id">

                <div class="txn-line__full">
                    <label class="txn-line__label label">{{ __('Product') }}</label>
                    {{-- Select2: searchable product + current stock qty in label; native name keeps save working. --}}
                    <select :name="`items[${i}][product_id]`"
                            class="input input-sm js-txn-product js-sale-product"
                            required
                            x-init="$nextTick(() => mountProductSelect($el, line))">
                    </select>
                    <p class="txn-line__meta" x-show="product(line)">
                        HSN <span x-text="product(line)?.hsn || '-'"></span>
                        <template x-if="available(line) !== null"><span> · {{ __('Stock here') }}: <strong x-text="available(line)"></strong> <span x-text="product(line)?.unit || ''"></span></span></template>
                        <template x-if="product(line)"><span> · {{ __('All places') }}: <strong x-text="product(line)?.stock ?? 0"></strong></span></template>
                        <template x-if="batch(line)?.rack"><span> · <span x-text="batch(line).rack"></span></span></template>
                        <template x-if="stockPlaces(line).length > 1"><span class="block text-leaf-dark">{{ __('By place') }}: <span x-text="stockPlacesLabel(line)"></span></span></template>
                        <template x-if="batch(line)?.mfg"><span class="lg:hidden"> · mfg <span x-text="batch(line).mfg"></span></span></template>
                        <template x-if="batch(line)?.expiry"><span class="lg:hidden"> · exp <span x-text="batch(line).expiry"></span></span></template>
                    </p>
                </div>

                <div class="txn-line__full lg:contents">
                    <label class="txn-line__label label">{{ __('Batch') }}</label>
                    <template x-if="product(line) && product(line).batch_required && batches(line).length">
                        <select :name="`items[${i}][batch_id]`" x-model="line.batch_id" @change="pickBatch(line)" class="input input-sm" :required="product(line).batch_required">
                            <template x-for="b in batches(line)" :key="b.id">
                                <option :value="b.id" :selected="String(b.id) === String(line.batch_id)"
                                        x-text="b.batch_no + (b.rack ? ' · ' + b.rack : '') + (b.expiry ? ' · exp ' + b.expiry : '') + ' · ' + b.qty"></option>
                            </template>
                        </select>
                    </template>
                    <span x-show="product(line) && product(line).batch_required && !batches(line).length" class="text-xs font-semibold text-red-600">{{ __('Out of stock here') }}</span>
                    <span x-show="!product(line) || !product(line).batch_required" class="hidden text-xs text-ink-soft lg:inline">—</span>
                </div>

                <div>
                    <label class="txn-line__label label">{{ __('Qty') }}</label>
                    <input type="number" inputmode="decimal" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm w-full" required
                           :class="overStock(line) ? 'border-red-500' : ''">
                    <p class="text-xs text-red-600" x-show="overStock(line)">{{ __('Exceeds stock') }}</p>
                </div>
                <div>
                    <label class="txn-line__label label">{{ __('Rate') }}</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm w-full" required>
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
                    <p class="txn-line__meta" x-show="line.gst_overridden" x-cloak>
                        {{ __('Default') }} <span x-text="line.gst_default"></span>%
                    </p>
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
