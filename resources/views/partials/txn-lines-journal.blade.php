{{-- Responsive stock-journal lines: card stack on phone, table grid on desktop. --}}
@php
    $isPhysical = ($type ?? '') === 'physical';
    $isJournal = ($type ?? '') === 'journal';
    $isTransfer = ($type ?? '') === 'transfer';
    $showLinePlace = ! $isTransfer;
@endphp
<div class="txn-lines">
    <div class="txn-line-head txn-line__grid txn-line__grid--journal"
         :class="{
             'txn-line__grid--multi-place': places.length > 1 && {{ $showLinePlace ? 'true' : 'false' }},
             'txn-line__grid--with-side': {{ $isJournal ? 'true' : 'false' }},
             'txn-line__grid--physical': {{ $isPhysical ? 'true' : 'false' }},
         }">
        @if ($isJournal)
            <span>{{ __('Side') }}</span>
        @endif
        @if ($showLinePlace)
            <span x-show="places.length > 1">{{ __('Place') }}</span>
        @endif
        <span>{{ __('Product') }}</span>
        <span>{{ __('Batch') }}</span>
        @if ($isPhysical)
            <span class="text-right">{{ __('System') }}</span>
            <span>{{ __('Counted') }}</span>
        @else
            <span>{{ __('Qty') }}</span>
        @endif
        <span>{{ __('Rate') }}</span>
        <span></span>
    </div>

    <template x-for="(line, i) in lines" :key="line.key">
        <div class="txn-line" data-line>
            <div class="txn-line__grid txn-line__grid--journal"
                 :class="{
                     'txn-line__grid--multi-place': places.length > 1 && {{ $showLinePlace ? 'true' : 'false' }},
                     'txn-line__grid--with-side': {{ $isJournal ? 'true' : 'false' }},
                     'txn-line__grid--physical': {{ $isPhysical ? 'true' : 'false' }},
                 }">
                @if ($isJournal)
                    <div>
                        <label class="txn-line__label label">{{ __('Side') }}</label>
                        <select :name="`items[${i}][side]`" x-model="line.side" @change="sideChanged(line)" class="input input-sm w-full">
                            <option value="out">{{ __('Out / consume') }}</option>
                            <option value="in">{{ __('In / produce') }}</option>
                        </select>
                    </div>
                @elseif (($type ?? '') === 'return')
                    <input type="hidden" :name="`items[${i}][side]`" value="in">
                @else
                    <input type="hidden" :name="`items[${i}][side]`" value="out">
                @endif

                @if ($showLinePlace)
                    <div x-show="places.length > 1" class="txn-line__full lg:contents">
                        <label class="txn-line__label label">{{ __('Place') }}</label>
                        <select :name="places.length > 1 ? `items[${i}][godown_id]` : ''" x-model="line.godown_id" @change="placeChanged(line)" class="input input-sm" :required="places.length > 1">
                            <template x-for="g in places" :key="g.id">
                                <option :value="g.id" :selected="String(g.id) === String(line.godown_id)"
                                        x-text="g.name + (stockPlaces(line).find(s => String(s.godown_id) === String(g.id)) ? ' (' + stockPlaces(line).find(s => String(s.godown_id) === String(g.id)).qty + ')' : '')"></option>
                            </template>
                        </select>
                    </div>
                    <input type="hidden" :name="places.length <= 1 ? `items[${i}][godown_id]` : ''" :value="line.godown_id">
                @else
                    <input type="hidden" :name="`items[${i}][godown_id]`" :value="defaultPlaceId">
                @endif

                <div class="txn-line__full">
                    <label class="txn-line__label label">{{ __('Product') }}</label>
                    <select :name="`items[${i}][product_id]`"
                            class="input input-sm js-txn-product"
                            required
                            x-init="$nextTick(() => mountProductSelect($el, line))">
                    </select>
                    <p class="txn-line__meta" x-show="product(line)">
                        <template x-if="available(line) !== null"><span>{{ __('Here') }}: <span x-text="available(line)"></span></span></template>
                        <template x-if="batch(line)?.rack"><span> · <span x-text="batch(line).rack"></span></span></template>
                        <template x-if="stockPlaces(line).length > 1"><span class="block text-leaf-dark">{{ __('By place') }}: <span x-text="stockPlacesLabel(line)"></span></span></template>
                    </p>
                </div>

                <div class="txn-line__full lg:contents">
                    <label class="txn-line__label label">{{ __('Batch') }}</label>
                    <template x-if="line.side !== 'in' && type !== 'return' && product(line)?.batch_required && batches(line).length">
                        <select :name="`items[${i}][batch_id]`" x-model="line.batch_id" class="input input-sm" required>
                            <template x-for="b in batches(line)" :key="b.id">
                                <option :value="b.id" :selected="String(b.id) === String(line.batch_id)"
                                        x-text="b.batch_no + (b.rack ? ' · ' + b.rack : '') + ' · ' + b.qty"></option>
                            </template>
                        </select>
                    </template>
                    <template x-if="line.side === 'in' || type === 'return'">
                        <input :name="`items[${i}][batch_no]`" x-model="line.batch_no" class="input input-sm"
                               :required="product(line)?.batch_required" :disabled="product(line) && !product(line).batch_required"
                               :placeholder="product(line)?.batch_required ? '{{ __('Batch no') }}' : 'NA'">
                    </template>
                </div>

                @if ($isPhysical)
                    <div>
                        <label class="txn-line__label label">{{ __('System') }}</label>
                        <div class="input input-sm bg-sage/30 text-ink-soft" x-text="systemQty(line)"></div>
                    </div>
                    <div>
                        <label class="txn-line__label label">{{ __('Counted') }}</label>
                        <input type="number" inputmode="decimal" step="0.001" min="0" :name="`items[${i}][counted_qty]`" x-model="line.counted_qty" class="input input-sm w-full" required>
                    </div>
                @else
                    <div>
                        <label class="txn-line__label label">{{ __('Qty') }}</label>
                        <input type="number" inputmode="decimal" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm w-full" required>
                    </div>
                @endif

                <div>
                    <label class="txn-line__label label">{{ __('Rate') }}</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm w-full">
                </div>

                <div class="col-span-2 flex items-end justify-end lg:col-span-1">
                    <button type="button" class="btn-danger btn-sm" @click="remove(i)" title="{{ __('Remove') }}">&times;</button>
                </div>
            </div>
        </div>
    </template>
</div>
