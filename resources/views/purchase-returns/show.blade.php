<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase return') }} {{ $return->return_no }}</h1>
    </x-slot>

    <div class="mb-4 flex justify-between no-print">
        <a href="{{ route('purchase-returns.index') }}" class="btn-secondary">&larr; {{ __('Back') }}</a>
    </div>

    <div class="card card-body mb-6 grid gap-3 text-sm sm:grid-cols-4">
        <div><span class="text-ink-soft">{{ __('Supplier') }}</span><p class="font-semibold">{{ $return->supplier->display_name }}</p></div>
        <div><span class="text-ink-soft">{{ __('Date') }}</span><p class="font-semibold">{{ fmt_date($return->return_date) }}</p></div>
        <div><span class="text-ink-soft">{{ __('Against purchase') }}</span>
            <p class="font-semibold"><a class="text-leaf hover:underline" href="{{ route('purchases.show', $return->purchase) }}">{{ $return->purchase->purchase_no }}</a></p></div>
        <div><span class="text-ink-soft">{{ __('Reason') }}</span><p class="font-semibold">{{ $return->reason ?: '-' }}</p></div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>{{ __('Product') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Rate') }}</th><th class="num">{{ __('Taxable') }}</th>
                    <th class="num">{{ __('GST %') }}</th><th class="num">{{ __('Total') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($return->items as $it)
                    <tr>
                        <td class="font-semibold">{{ $it->product->display_name }}</td>
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

    <div class="card card-body ml-auto mt-6 max-w-sm space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-ink-soft">{{ __('Taxable value') }}</span><span>{{ money($return->taxable_total) }}</span></div>
        @if ($return->is_igst)
            <div class="flex justify-between"><span class="text-ink-soft">IGST</span><span>{{ money($return->igst_total) }}</span></div>
        @else
            <div class="flex justify-between"><span class="text-ink-soft">CGST</span><span>{{ money($return->cgst_total) }}</span></div>
            <div class="flex justify-between"><span class="text-ink-soft">SGST</span><span>{{ money($return->sgst_total) }}</span></div>
        @endif
        <div class="flex justify-between border-t border-sage pt-2 font-display text-lg font-semibold text-leaf-dark"><span>{{ __('Return amount') }}</span><span>&#8377; {{ money($return->grand_total) }}</span></div>
    </div>
</x-app-layout>
