{{-- Live totals panel inside an invoiceForm() Alpine scope. --}}
<div class="card card-body space-y-3 text-sm">
    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Subtotal') }}</span><span class="tabular-nums" x-text="money(totals.gross)"></span></div>
    <div class="flex justify-between" x-show="totals.disc > 0"><span class="text-ink-soft">{{ __('Line discount') }}</span><span class="tabular-nums" x-text="'- ' + money(totals.disc)"></span></div>
    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span class="tabular-nums" x-text="money(totals.taxable)"></span></div>
    <template x-if="!inter">
        <div class="space-y-3">
            <div class="flex justify-between"><span class="text-ink-soft">CGST</span><span class="tabular-nums" x-text="money(totals.cgst)"></span></div>
            <div class="flex justify-between"><span class="text-ink-soft">SGST</span><span class="tabular-nums" x-text="money(totals.sgst)"></span></div>
        </div>
    </template>
    <div class="flex justify-between" x-show="inter"><span class="text-ink-soft">IGST</span><span class="tabular-nums" x-text="money(totals.igst)"></span></div>

    <div class="grid gap-2 border-t border-sage pt-3 sm:grid-cols-3">
        <div>
            <label class="label" for="f-freight_amount">{{ __('Freight') }}</label>
            <input id="f-freight_amount" type="number" step="0.01" min="0" name="freight_amount"
                   class="input input-sm" x-model.number="freightAmount">
        </div>
        <div>
            <label class="label" for="f-hammali_amount">{{ __('Hammali') }}</label>
            <input id="f-hammali_amount" type="number" step="0.01" min="0" name="hammali_amount"
                   class="input input-sm" x-model.number="hammaliAmount">
        </div>
        <div>
            <label class="label" for="f-cash_discount">{{ __('Cash discount') }}</label>
            <input id="f-cash_discount" type="number" step="0.01" min="0" name="cash_discount"
                   class="input input-sm" x-model.number="cashDiscount">
        </div>
    </div>
    @error('freight_amount') <p class="error-text">{{ $message }}</p> @enderror
    @error('hammali_amount') <p class="error-text">{{ $message }}</p> @enderror
    @error('cash_discount') <p class="error-text">{{ $message }}</p> @enderror

    <div class="flex justify-between" x-show="totals.freight > 0"><span class="text-ink-soft">{{ __('Freight') }}</span><span class="tabular-nums" x-text="money(totals.freight)"></span></div>
    <div class="flex justify-between" x-show="totals.hammali > 0"><span class="text-ink-soft">{{ __('Hammali') }}</span><span class="tabular-nums" x-text="money(totals.hammali)"></span></div>
    <div class="flex justify-between" x-show="totals.cash > 0"><span class="text-ink-soft">{{ __('Cash discount') }}</span><span class="tabular-nums" x-text="'- ' + money(totals.cash)"></span></div>
    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Round off') }}</span><span class="tabular-nums" x-text="money(totals.round)"></span></div>
    <div class="flex justify-between border-t border-sage pt-3 font-display text-lg font-semibold text-leaf-dark">
        <span>{{ __('Grand total') }}</span><span class="tabular-nums">&#8377; <span x-text="money(totals.grand)"></span></span>
    </div>
    <p class="text-xs" :class="inter ? 'text-accent font-semibold' : 'text-ink-soft'">
        <span x-show="inter">{{ __('Inter-state supply: IGST applies.') }}</span>
        <span x-show="!inter">{{ __('Intra-state supply: CGST + SGST apply.') }}</span>
    </p>
    <p class="text-[11px] text-ink-soft">{{ __('Freight & Hammali post to Account map ledgers.') }}</p>
</div>
