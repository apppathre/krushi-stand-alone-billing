<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Invoice') }} {{ $sale->invoice_no }}</h1>
    </x-slot>

    @php
        $partyName = $sale->customer_name ?: $sale->client->display_name;
        $partyPhone = $sale->customer_phone ?: $sale->client->phone;
        $lineCount = $sale->items->count();
        $due = (float) $sale->balance;
        $needsPay = ! $sale->isCancelled() && $due > 0.004;
    @endphp

    <div class="bill-summary">
        <div class="bill-summary__bar no-print">
            <a href="{{ route('sales.index') }}" class="btn-secondary btn-sm">&larr; {{ __('Back') }}</a>
            <div class="bill-summary__bar-actions">
                @include('partials.invoice-print-menu', ['sale' => $sale])
                <a href="{{ route('sales.details', $sale) }}" class="btn-secondary btn-sm">{{ __('Full details') }}</a>
            </div>
        </div>

        <div class="bill-summary__hero card card-body">
            <div class="bill-summary__hero-main">
                <div class="min-w-0">
                    <p class="bill-summary__party">{{ $partyName }}</p>
                    <p class="bill-summary__meta">
                        @if ($partyPhone)<span>{{ $partyPhone }}</span>@endif
                        <span>{{ fmt_date($sale->invoice_date) }}</span>
                        <span>{{ $sale->godown->display_name }}</span>
                        <span>{{ $sale->is_igst ? 'IGST' : 'CGST+SGST' }}</span>
                    </p>
                </div>
                <div class="bill-summary__hero-right">
                    <x-sale-status :sale="$sale" />
                    <p class="bill-summary__total">&#8377; {{ money($sale->grand_total) }}</p>
                    @if ($sale->isCancelled())
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
                @can('menu', ['sales', 'add'])
                    @include('partials.payment-box', [
                        'action' => route('sales.payments.store', $sale),
                        'doc' => $sale,
                        'title' => __('Receive payment'),
                    ])
                @endcan

                @unless ($needsPay)
                    <div class="card card-body space-y-1.5 text-sm">
                        <div class="flex justify-between font-display text-base font-semibold text-leaf-dark">
                            <span>{{ __('Grand total') }}</span>
                            <span>&#8377; {{ money($sale->grand_total) }}</span>
                        </div>
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Paid') }}</span><span>{{ money($sale->paid_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Balance due') }}</span><span>{{ money($sale->balance) }}</span></div>
                    </div>
                @endunless

                @if ($sale->payments->isNotEmpty())
                    <div class="card card-body text-sm">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <h3 class="card-title !mb-0 !text-base">{{ __('Payments') }}</h3>
                            <a href="{{ route('sales.details', $sale) }}#payments" class="text-xs font-semibold text-leaf hover:underline">{{ __('Full details') }}</a>
                        </div>
                        @foreach ($sale->payments->take(3) as $pay)
                            <div class="flex justify-between border-t border-sage py-1.5">
                                <span>{{ fmt_date($pay->payment_date) }} · {{ $pay->ledger?->display_name ?: strtoupper($pay->mode) }}</span>
                                <span>{{ money($pay->amount) }}</span>
                            </div>
                        @endforeach
                        @if ($sale->payments->count() > 3)
                            <p class="pt-1 text-xs text-ink-soft">+{{ $sale->payments->count() - 3 }} {{ __('more') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                <div class="card card-body">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="card-title !mb-0 !text-base">{{ __('Lines') }} ({{ $lineCount }})</h3>
                        <a href="{{ route('sales.details', $sale) }}" class="text-xs font-semibold text-leaf hover:underline">{{ __('View all lines') }} →</a>
                    </div>
                    <ul class="bill-summary__lines">
                        @foreach ($sale->items->take(5) as $it)
                            <li>
                                <span class="bill-summary__line-name">{{ $it->product->display_name }}</span>
                                <span class="bill-summary__line-qty">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}</span>
                                <span class="bill-summary__line-amt">{{ money($it->line_total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($lineCount > 5)
                        <p class="mt-2 text-xs text-ink-soft">+{{ $lineCount - 5 }} {{ __('more') }} — <a href="{{ route('sales.details', $sale) }}" class="font-semibold text-leaf hover:underline">{{ __('Full details') }}</a></p>
                    @endif
                </div>

                <div class="card card-body space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span>{{ money($sale->taxable_total) }}</span></div>
                    @if ($sale->is_igst)
                        <div class="flex justify-between"><span class="text-ink-soft">IGST</span><span>{{ money($sale->igst_total) }}</span></div>
                    @else
                        <div class="flex justify-between"><span class="text-ink-soft">CGST + SGST</span><span>{{ money($sale->cgst_total + $sale->sgst_total) }}</span></div>
                    @endif
                    @if ((float) ($sale->freight_amount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Freight') }}</span><span>{{ money($sale->freight_amount) }}</span></div>
                    @endif
                    @if ((float) ($sale->hammali_amount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Hammali') }}</span><span>{{ money($sale->hammali_amount) }}</span></div>
                    @endif
                    @if ((float) ($sale->cash_discount ?? 0) > 0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Cash discount') }}</span><span>- {{ money($sale->cash_discount) }}</span></div>
                    @endif
                    @if ((float) $sale->round_off != 0.0)
                        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Round off') }}</span><span>{{ money($sale->round_off) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-sage pt-1.5 font-semibold text-leaf-dark">
                        <span>{{ __('Grand total') }}</span><span>&#8377; {{ money($sale->grand_total) }}</span>
                    </div>
                    @if ($sale->due_date)
                        <div class="flex justify-between text-xs text-ink-soft">
                            <span>{{ __('Due') }}</span>
                            <span>{{ fmt_date($sale->due_date) }}@if ($sale->credit_days) · {{ $sale->credit_days }} {{ __('days') }}@endif</span>
                        </div>
                    @endif
                </div>

                <div class="bill-summary__more no-print flex flex-wrap gap-2">
                    @can('menu', ['sale_returns', 'add'])
                        @unless ($sale->isCancelled())
                            <a href="{{ route('sale-returns.create', ['sale_id' => $sale->id]) }}" class="btn-secondary btn-sm">{{ __('Create credit note') }}</a>
                        @endunless
                    @endcan
                    @can('menu', ['sales', 'delete'])
                        @unless ($sale->isCancelled())
                            <button type="button" class="btn-danger btn-sm"
                                    data-confirm-submit
                                    data-confirm-message="{{ __('Cancel this invoice? Stock and ledger entries will be reversed.') }}"
                                    data-confirm-action="{{ route('sales.cancel', $sale) }}"
                                    data-confirm-method="DELETE">
                                {{ __('Cancel invoice') }}
                            </button>
                        @endunless
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
