<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Sales Register') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-40" />
        <x-select-field name="godown_id" :label="__('Place')" :value="request('godown_id')" :placeholder="__('All')"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-44" />
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Client') }}</th><th>{{ __('GSTIN') }}</th>
                        <th class="num">{{ __('Taxable') }}</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th>
                        <th class="num">{{ __('Round off') }}</th><th class="num">{{ __('Total') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($sales as $s)
                        <tr>
                            <td>{{ fmt_date($s->invoice_date) }}</td>
                            <td><a href="{{ route('sales.show', $s) }}" class="font-semibold text-leaf hover:underline">{{ $s->invoice_no }}</a></td>
                            <td>{{ $s->customer_name ?: $s->client->display_name }}</td>
                            <td>{{ $s->client->gstin }}</td>
                            <td class="num">{{ money($s->taxable_total) }}</td>
                            <td class="num">{{ money($s->cgst_total) }}</td>
                            <td class="num">{{ money($s->sgst_total) }}</td>
                            <td class="num">{{ money($s->igst_total) }}</td>
                            <td class="num">{{ money($s->round_off) }}</td>
                            <td class="num font-semibold">{{ money($s->grand_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                    <tr class="bg-sage/50 font-bold">
                        <td colspan="4">{{ __('Total sales') }}</td>
                        <td class="num">{{ money($totals['taxable_total']) }}</td><td class="num">{{ money($totals['cgst_total']) }}</td>
                        <td class="num">{{ money($totals['sgst_total']) }}</td><td class="num">{{ money($totals['igst_total']) }}</td>
                        <td class="num">{{ money($totals['round_off']) }}</td><td class="num">{{ money($totals['grand_total']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($returns->isNotEmpty())
            <h2 class="card-title mb-2 mt-6">{{ __('Credit notes (sales returns)') }}</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>{{ __('Date') }}</th><th>{{ __('Credit note') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Client') }}</th>
                            <th class="num">{{ __('Taxable') }}</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">{{ __('Total') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $r)
                            <tr>
                                <td>{{ fmt_date($r->return_date) }}</td>
                                <td><a href="{{ route('sale-returns.show', $r) }}" class="font-semibold text-leaf hover:underline">{{ $r->return_no }}</a></td>
                                <td>{{ $r->sale->invoice_no }}</td>
                                <td>{{ $r->client->display_name }}</td>
                                <td class="num">{{ money($r->taxable_total) }}</td><td class="num">{{ money($r->cgst_total) }}</td>
                                <td class="num">{{ money($r->sgst_total) }}</td><td class="num">{{ money($r->igst_total) }}</td>
                                <td class="num font-semibold">{{ money($r->grand_total) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-sage/50 font-bold">
                            <td colspan="4">{{ __('Total returns') }}</td>
                            <td class="num">{{ money($return_totals['taxable_total']) }}</td><td class="num">{{ money($return_totals['cgst_total']) }}</td>
                            <td class="num">{{ money($return_totals['sgst_total']) }}</td><td class="num">{{ money($return_totals['igst_total']) }}</td>
                            <td class="num">{{ money($return_totals['grand_total']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-right font-display text-lg font-semibold text-leaf-dark">
                {{ __('Net sales') }}: &#8377; {{ money($totals['grand_total'] - $return_totals['grand_total']) }}
            </p>
        @endif
    </div>
</x-app-layout>
