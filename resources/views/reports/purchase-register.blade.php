<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase Register') }}</h1>
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
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Purchase no') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Supplier invoice') }}</th><th>{{ __('GSTIN') }}</th>
                        <th class="num">{{ __('Taxable') }}</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">{{ __('Total') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $p)
                        <tr>
                            <td>{{ fmt_date($p->purchase_date) }}</td>
                            <td><a href="{{ route('purchases.show', $p) }}" class="font-semibold text-leaf hover:underline">{{ $p->purchase_no }}</a></td>
                            <td>{{ $p->supplier->display_name }}</td>
                            <td>{{ $p->supplier_invoice_no }}</td>
                            <td>{{ $p->supplier->gstin }}</td>
                            <td class="num">{{ money($p->taxable_total) }}</td>
                            <td class="num">{{ money($p->cgst_total) }}</td>
                            <td class="num">{{ money($p->sgst_total) }}</td>
                            <td class="num">{{ money($p->igst_total) }}</td>
                            <td class="num font-semibold">{{ money($p->grand_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                    <tr class="bg-sage/50 font-bold">
                        <td colspan="5">{{ __('Total purchases') }}</td>
                        <td class="num">{{ money($totals['taxable_total']) }}</td><td class="num">{{ money($totals['cgst_total']) }}</td>
                        <td class="num">{{ money($totals['sgst_total']) }}</td><td class="num">{{ money($totals['igst_total']) }}</td>
                        <td class="num">{{ money($totals['grand_total']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
