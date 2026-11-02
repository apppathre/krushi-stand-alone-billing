<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Bill details') }} · {{ $purchase->purchase_no }}</h1>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 no-print">
        <a href="{{ route('purchases.show', $purchase) }}" class="btn-secondary btn-sm">&larr; {{ __('Summary') }}</a>
        <button type="button" onclick="window.print()" class="btn-secondary btn-sm">{{ __('Print') }}</button>
    </div>

    <div class="card card-body mb-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <span class="text-ink-soft">{{ __('Supplier') }}</span>
            <p class="font-semibold">{{ $purchase->supplier->display_name }}</p>
            <p class="text-xs text-ink-soft">{{ $purchase->supplier->gstin }}</p>
        </div>
        <div><span class="text-ink-soft">{{ __('Date') }}</span><p class="font-semibold">{{ fmt_date($purchase->purchase_date) }}</p></div>
        <div>
            <span class="text-ink-soft">{{ __('Supplier invoice') }}</span>
            <p class="font-semibold">{{ $purchase->supplier_invoice_no ?: '-' }} {{ $purchase->supplier_invoice_date ? '('.fmt_date($purchase->supplier_invoice_date).')' : '' }}</p>
        </div>
        <div><span class="text-ink-soft">{{ __('Godown') }}</span><p class="font-semibold">{{ $purchase->godown->display_name }}</p></div>
        <div><span class="text-ink-soft">{{ __('Tax type') }}</span><p class="font-semibold">{{ $purchase->is_igst ? 'IGST' : 'CGST + SGST' }}</p></div>
        <div>@if ($purchase->isCancelled()) <span class="badge-gray">{{ __('Cancelled') }}</span> @endif</div>
    </div>

    <div class="table-wrap mb-4">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th>{{ __('Mfg') }}</th>
                    <th>{{ __('Expiry') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    <th class="num">{{ __('Rate') }}</th>
                    <th class="num">{{ __('Taxable') }}</th>
                    <th class="num">{{ __('GST %') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $i => $it)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="font-semibold">{{ $it->product->display_name }}</td>
                        <td>{{ $it->batch_no }}</td>
                        <td>{{ fmt_date($it->mfg_date) }}</td>
                        <td>{{ fmt_date($it->expiry_date) }}</td>
                        <td class="num">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}</td>
                        <td class="num">{{ money($it->rate) }}</td>
                        <td class="num">{{ money($it->taxable_amount) }}</td>
                        <td class="num">{{ qty($it->gst_rate) }}</td>
                        <td class="num">{{ money($it->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mb-4 grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @php($showTerms = \App\Support\TermsHtml::sanitize($purchase->terms))
            @if ($showTerms)
                <div class="card card-body text-sm prose-terms">
                    <h3 class="card-title mb-2">{{ __('Terms & conditions') }}</h3>
                    <div class="text-ink-soft">{!! $showTerms !!}</div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card card-body space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span>{{ money($purchase->taxable_total) }}</span></div>
                @if ($purchase->is_igst)
                    <div class="flex justify-between"><span class="text-ink-soft">IGST</span><span>{{ money($purchase->igst_total) }}</span></div>
                @else
                    <div class="flex justify-between"><span class="text-ink-soft">CGST</span><span>{{ money($purchase->cgst_total) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-soft">SGST</span><span>{{ money($purchase->sgst_total) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Round off') }}</span><span>{{ money($purchase->round_off) }}</span></div>
                <div class="flex justify-between border-t border-sage pt-2 font-display text-lg font-semibold text-leaf-dark">
                    <span>{{ __('Grand total') }}</span><span>&#8377; {{ money($purchase->grand_total) }}</span>
                </div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Paid') }}</span><span>{{ money($purchase->paid_amount) }}</span></div>
                <div class="flex justify-between font-semibold text-accent"><span>{{ __('Balance due') }}</span><span>{{ money($purchase->balance) }}</span></div>
            </div>

            <div id="payments" class="card card-body text-sm">
                <h3 class="card-title mb-2">{{ __('Payments') }}</h3>
                @forelse ($purchase->payments as $pay)
                    <div class="flex justify-between border-t border-sage py-1.5">
                        <span>{{ fmt_date($pay->payment_date) }} · {{ $pay->ledger?->display_name ?: strtoupper($pay->mode) }}</span>
                        <span>{{ money($pay->amount) }}</span>
                    </div>
                @empty
                    <p class="text-ink-soft">{{ __('No payments yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
