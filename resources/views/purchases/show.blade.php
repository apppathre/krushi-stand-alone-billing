<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase') }} {{ $purchase->purchase_no }}</h1>
    </x-slot>

    @php
        $lineCount = $purchase->items->count();
        $due = (float) $purchase->balance;
        $needsPay = ! $purchase->isCancelled() && $due > 0.004;
    @endphp

    <div class="bill-summary">
        <div class="bill-summary__bar no-print">
            <a href="{{ route('purchases.index') }}" class="btn-secondary btn-sm">&larr; {{ __('Back') }}</a>
            <div class="bill-summary__bar-actions">
                <button type="button" onclick="window.print()" class="btn-accent btn-sm">{{ __('Print') }}</button>
                <a href="{{ route('purchases.details', $purchase) }}" class="btn-secondary btn-sm">{{ __('Full details') }}</a>
            </div>
        </div>

        <div class="bill-summary__hero card card-body">
            <div class="bill-summary__hero-main">
                <div class="min-w-0">
                    <p class="bill-summary__party">{{ $purchase->supplier->display_name }}</p>
                    <p class="bill-summary__meta">
                        @if ($purchase->supplier->gstin)<span>{{ $purchase->supplier->gstin }}</span>@endif
                        <span>{{ fmt_date($purchase->purchase_date) }}</span>
                        <span>{{ $purchase->godown->display_name }}</span>
                        <span>{{ $purchase->is_igst ? 'IGST' : 'CGST+SGST' }}</span>
                        @if ($purchase->supplier_invoice_no)
                            <span>{{ __('Supplier invoice') }}: {{ $purchase->supplier_invoice_no }}</span>
                        @endif
                    </p>
                </div>
                <div class="bill-summary__hero-right">
                    @if ($purchase->isCancelled())
                        <span class="badge-gray">{{ __('Cancelled') }}</span>
                    @elseif ($needsPay && (float) $purchase->paid_amount > 0.004)
                        <span class="badge-amber">{{ __('Partial') }}</span>
                    @elseif ($needsPay)
                        <span class="badge-red">{{ __('Unpaid') }}</span>
                    @else
                        <span class="badge-green">{{ __('Paid') }}</span>
                    @endif
                    <p class="bill-summary__total">&#8377; {{ money($purchase->grand_total) }}</p>
                    @if ($purchase->isCancelled())
                        <p class="bill-summary__due text-ink-soft">{{ __('Cancelled') }}</p>
                    @elseif ($needsPay)
                        <p class="bill-summary__due">{{ __('Balance due') }}: &#8377; {{ money($due) }}</p>
                    @else
                        <p class="bill-summary__due is-paid">{{ __('Paid') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="bill-summary__grid">
            <div class="bill-summary__primary space-y-3">
                @can('menu', ['purchases', 'add'])
                    @include('partials.payment-box', [
                        'action' => route('purchases.payments.store', $purchase),
                        'doc' => $purchase,
                        'title' => __('Pay supplier'),
                    ])
                @endcan

                @unless ($needsPay)
                    <div class="card card-body space-y-1.5 text-sm">
                        <div class="flex justify-between font-display text-base font-semibold text-leaf-dark">
                            <span>{{ __('Grand total') }}</span>
                            <span>&#8377; {{ money($purchase->grand_total) }}</span>
                        </div>
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Paid') }}</span><span>{{ money($purchase->paid_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Balance due') }}</span><span>{{ money($purchase->balance) }}</span></div>
                    </div>
                @endunless

                @if ($purchase->payments->isNotEmpty())
                    <div class="card card-body text-sm">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <h3 class="card-title !mb-0 !text-base">{{ __('Payments') }}</h3>
                            <a href="{{ route('purchases.details', $purchase) }}#payments" class="text-xs font-semibold text-leaf hover:underline">{{ __('Full details') }}</a>
                        </div>
                        @foreach ($purchase->payments->take(3) as $pay)
                            <div class="flex justify-between border-t border-sage py-1.5">
                                <span>{{ fmt_date($pay->payment_date) }} · {{ $pay->ledger?->display_name ?: strtoupper($pay->mode) }}</span>
                                <span>{{ money($pay->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                <div class="card card-body">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="card-title !mb-0 !text-base">{{ __('Lines') }} ({{ $lineCount }})</h3>
                        <a href="{{ route('purchases.details', $purchase) }}" class="text-xs font-semibold text-leaf hover:underline">{{ __('View all lines') }} →</a>
                    </div>
                    <ul class="bill-summary__lines">
                        @foreach ($purchase->items->take(5) as $it)
                            <li>
                                <span class="bill-summary__line-name">{{ $it->product->display_name }}</span>
                                <span class="bill-summary__line-qty">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}</span>
                                <span class="bill-summary__line-amt">{{ money($it->line_total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($lineCount > 5)
                        <p class="mt-2 text-xs text-ink-soft">+{{ $lineCount - 5 }} {{ __('more') }} — <a href="{{ route('purchases.details', $purchase) }}" class="font-semibold text-leaf hover:underline">{{ __('Full details') }}</a></p>
                    @endif
                </div>

                <div class="card card-body space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span>{{ money($purchase->taxable_total) }}</span></div>
                    @if ($purchase->is_igst)
                        <div class="flex justify-between"><span class="text-ink-soft">IGST</span><span>{{ money($purchase->igst_total) }}</span></div>
                    @else
                        <div class="flex justify-between"><span class="text-ink-soft">CGST + SGST</span><span>{{ money($purchase->cgst_total + $purchase->sgst_total) }}</span></div>
                    @endif
                    @if ((float) ($purchase->freight_amount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Freight') }}</span><span>{{ money($purchase->freight_amount) }}</span></div>
                    @endif
                    @if ((float) ($purchase->hammali_amount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Hammali') }}</span><span>{{ money($purchase->hammali_amount) }}</span></div>
                    @endif
                    @if ((float) ($purchase->cash_discount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Cash discount') }}</span><span>- {{ money($purchase->cash_discount) }}</span></div>
                    @endif
                    @if ((float) $purchase->round_off != 0.0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Round off') }}</span><span>{{ money($purchase->round_off) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-sage pt-1.5 font-semibold text-leaf-dark">
                        <span>{{ __('Grand total') }}</span><span>&#8377; {{ money($purchase->grand_total) }}</span>
                    </div>
                    @if ($purchase->due_date)
                        <div class="flex justify-between text-xs text-ink-soft">
                            <span>{{ __('Due') }}</span>
                            <span>{{ fmt_date($purchase->due_date) }}@if ($purchase->credit_days) · {{ $purchase->credit_days }} {{ __('days') }}@endif</span>
                        </div>
                    @endif
                </div>

                <div class="bill-summary__more no-print flex flex-wrap gap-2">
                    @can('menu', ['purchases', 'delete'])
                        @unless ($purchase->isCancelled())
                            <button type="button" class="btn-danger btn-sm"
                                    data-confirm-submit
                                    data-confirm-message="{{ __('Cancel this purchase? Stock and ledger entries will be reversed.') }}"
                                    data-confirm-action="{{ route('purchases.cancel', $purchase) }}"
                                    data-confirm-method="DELETE">
                                {{ __('Cancel purchase') }}
                            </button>
                        @endunless
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
