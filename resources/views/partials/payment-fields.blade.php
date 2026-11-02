{{-- Settlement + optional payment. Needs invoiceForm() scope. --}}
@php $askSettlement = $askSettlement ?? true; @endphp
<div class="card card-body space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="card-title !mb-0">{{ __('Payment') }}</h3>
        @if ($askSettlement)
            <div class="pay-settle" role="radiogroup" aria-label="{{ __('How is this bill settled?') }}">
                <label class="pay-settle__opt" :class="settlement === 'credit' && 'is-on'">
                    <input type="radio" name="settlement_type" value="credit" class="sr-only" x-model="settlement" @change="onSettlementChange()">
                    <span>{{ __('Credit') }}</span>
                </label>
                <label class="pay-settle__opt" :class="settlement === 'cash' && 'is-on'">
                    <input type="radio" name="settlement_type" value="cash" class="sr-only" x-model="settlement" @change="onSettlementChange()">
                    <span>{{ __('Cash') }}</span>
                </label>
            </div>
        @endif
    </div>
    @if ($askSettlement)
        @error('settlement_type') <p class="error-text">{{ $message }}</p> @enderror
        <div x-show="settlement === 'credit'" x-cloak class="grid gap-2 sm:grid-cols-[7rem_1fr] sm:items-end">
            <div>
                <label class="label" for="f-credit_days">{{ __('Credit days') }}</label>
                <input id="f-credit_days" type="number" min="0" max="365" step="1" name="credit_days"
                       class="input input-sm" x-model.number="creditDaysBill"
                       :required="settlement === 'credit'">
            </div>
            <p class="pb-1 text-xs text-ink-soft" x-text="creditDueLabel"></p>
            <input type="hidden" name="due_date" :value="dueDateIso">
        </div>
        @error('credit_days') <p class="error-text">{{ $message }}</p> @enderror
        <p class="text-xs text-ink-soft" x-show="settlement === 'cash'" x-cloak>{{ __('Paid now — pick Cash, Bank or UPI below.') }}</p>
    @endif

    <div x-show="settlement === 'cash'" x-cloak class="space-y-3">
        <div>
            <label class="label" for="paid_amount">{{ $label ?? __('Amount paid now') }}</label>
            <div class="flex gap-2">
                <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" x-model="paid" class="input"
                       :required="settlement === 'cash'">
                <button type="button" class="btn-secondary" @click="payFull()">{{ __('Full') }}</button>
            </div>
            @error('paid_amount') <p class="error-text">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @include('partials.payment-ledger-field', [
                'name' => 'payment_ledger_id',
                'label' => __('Paid from / Received in'),
                'required' => false,
                'hint' => __('How did you pay? Pick Cash, Bank, GPay, PhonePe…'),
            ])
            <x-field name="payment_reference" :label="__('Reference')" />
        </div>
    </div>

    <div class="flex justify-between text-sm">
        <span class="text-ink-soft">{{ __('Balance due') }}</span>
        <span class="font-semibold tabular-nums text-accent">&#8377; <span x-text="money(totals.balance)"></span></span>
    </div>
</div>
<style>
    .pay-settle {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem;
        border-radius: 999px;
        background: rgb(var(--sage-rgb) / 0.55);
        border: 1px solid rgb(197 217 203 / 0.95);
    }
    .pay-settle__opt {
        cursor: pointer;
        border-radius: 999px;
        padding: 0.35rem 0.85rem;
        font-size: 0.8rem;
        font-weight: 650;
        color: var(--ink-soft);
        line-height: 1.2;
        transition: background 0.12s ease, color 0.12s ease, box-shadow 0.12s ease;
    }
    .pay-settle__opt.is-on {
        background: #fff;
        color: var(--leaf-dark);
        box-shadow: var(--shadow-soft);
    }
</style>
