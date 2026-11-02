<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Bill details') }} · {{ $sale->invoice_no }}</h1>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 no-print">
        <a href="{{ route('sales.show', $sale) }}" class="btn-secondary btn-sm">&larr; {{ __('Summary') }}</a>
        <div class="flex flex-wrap gap-2">
            @include('partials.invoice-print-menu', ['sale' => $sale])
            @can('menu', ['sale_returns', 'add'])
                @unless ($sale->isCancelled())
                    <a href="{{ route('sale-returns.create', ['sale_id' => $sale->id]) }}" class="btn-secondary btn-sm">{{ __('Create credit note') }}</a>
                @endunless
            @endcan
        </div>
    </div>

    <div class="card card-body mb-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <span class="text-ink-soft">{{ __('Client') }}</span>
            <p class="font-semibold">{{ $sale->customer_name ?: $sale->client->display_name }}</p>
            <p class="text-xs text-ink-soft">{{ $sale->customer_phone ?: $sale->client->phone }} {{ $sale->client->gstin }}</p>
        </div>
        <div><span class="text-ink-soft">{{ __('Date') }}</span><p class="font-semibold">{{ fmt_date($sale->invoice_date) }}</p></div>
        <div><span class="text-ink-soft">{{ __('Godown') }}</span><p class="font-semibold">{{ $sale->godown->display_name }}</p></div>
        <div>
            <span class="text-ink-soft">{{ __('Place of supply') }}</span>
            <p class="font-semibold">{{ \App\Support\IndianStates::name($sale->supply_state_code) }} ({{ $sale->is_igst ? 'IGST' : 'CGST + SGST' }})</p>
        </div>
        <div><span class="text-ink-soft">{{ __('Billed by') }}</span><p class="font-semibold">{{ $sale->user?->name }}</p></div>
        <div><x-sale-status :sale="$sale" /></div>
    </div>

    <div class="table-wrap mb-4">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Place') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th>{{ __('Mfg') }}</th>
                    <th>{{ __('Expiry') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    <th class="num">{{ __('Rate') }}</th>
                    <th class="num">{{ __('Disc') }}</th>
                    <th class="num">{{ __('Taxable') }}</th>
                    <th class="num">{{ __('GST %') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $i => $it)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="font-semibold">{{ $it->product->display_name }}<span class="block text-xs font-normal text-ink-soft">HSN {{ $it->hsn_code }}</span></td>
                        <td>{{ $it->godown?->display_name ?? $sale->godown->display_name }}</td>
                        <td>{{ $it->batch?->rack?->label ?? '—' }}</td>
                        <td>{{ $it->batch && $it->batch->batch_no !== 'NA' ? $it->batch->batch_no : '' }}</td>
                        <td>{{ fmt_date($it->batch?->mfg_date) }}</td>
                        <td>{{ fmt_date($it->batch?->expiry_date) }}</td>
                        <td class="num">{{ qty($it->qty) }} {{ $it->product->unit?->short_name }}
                            @if ($it->returned_qty > 0)<span class="block text-xs text-accent">{{ __('Returned') }}: {{ qty($it->returned_qty) }}</span>@endif
                        </td>
                        <td class="num">{{ money($it->rate) }}</td>
                        <td class="num">{{ money($it->discount_amount) }}</td>
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
            @php($showTerms = \App\Support\TermsHtml::sanitize($sale->terms))
            @if ($showTerms)
                <div class="card card-body text-sm prose-terms">
                    <h3 class="card-title mb-2">{{ __('Terms & conditions') }}</h3>
                    <div class="text-ink-soft">{!! $showTerms !!}</div>
                </div>
            @endif

            @if ($sale->returns->isNotEmpty())
                <div class="card card-body text-sm">
                    <h3 class="card-title mb-2">{{ __('Credit notes') }}</h3>
                    @foreach ($sale->returns as $r)
                        <div class="flex justify-between border-t border-sage py-1.5">
                            <a class="font-semibold text-leaf hover:underline" href="{{ route('sale-returns.show', $r) }}">{{ $r->return_no }}</a>
                            <span>{{ fmt_date($r->return_date) }} · {{ money($r->grand_total) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card card-body space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Subtotal') }}</span><span>{{ money($sale->subtotal) }}</span></div>
                @if ($sale->discount_total > 0)
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Discount') }}</span><span>- {{ money($sale->discount_total) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span>{{ money($sale->taxable_total) }}</span></div>
                @if ($sale->is_igst)
                    <div class="flex justify-between"><span class="text-ink-soft">IGST</span><span>{{ money($sale->igst_total) }}</span></div>
                @else
                    <div class="flex justify-between"><span class="text-ink-soft">CGST</span><span>{{ money($sale->cgst_total) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-soft">SGST</span><span>{{ money($sale->sgst_total) }}</span></div>
                @endif
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Round off') }}</span><span>{{ money($sale->round_off) }}</span></div>
                <div class="flex justify-between border-t border-sage pt-2 font-display text-lg font-semibold text-leaf-dark">
                    <span>{{ __('Grand total') }}</span><span>&#8377; {{ money($sale->grand_total) }}</span>
                </div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Paid') }}</span><span>{{ money($sale->paid_amount) }}</span></div>
                <div class="flex justify-between font-semibold text-accent"><span>{{ __('Balance due') }}</span><span>{{ money($sale->balance) }}</span></div>
            </div>

            <div id="payments" class="card card-body text-sm">
                <h3 class="card-title mb-2">{{ __('Payments') }}</h3>
                @forelse ($sale->payments as $pay)
                    <div class="flex justify-between border-t border-sage py-1.5">
                        <span>{{ fmt_date($pay->payment_date) }} · {{ $pay->ledger?->display_name ?: strtoupper($pay->mode) }}
                            @if ($pay->reference)<span class="block text-xs text-ink-soft">{{ $pay->reference }}</span>@endif
                        </span>
                        <span>{{ money($pay->amount) }}</span>
                    </div>
                @empty
                    <p class="text-ink-soft">{{ __('No payments yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
