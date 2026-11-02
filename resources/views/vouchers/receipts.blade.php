<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Receipt') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Bill receipts')" :blurb="__('Collect against open sale invoices. For rent / other income use Receipt voucher.')">
        @can('menu', ['account_receipts', 'view'])
            <a href="{{ route('account-vouchers.receipt') }}" class="btn-secondary btn-sm">{{ __('Receipt voucher') }}</a>
        @endcan
        @can('menu', ['bulk_receipt', 'view'])
            <a href="{{ route('bulk-receipts.create') }}" class="btn-secondary btn-sm">{{ __('Bulk receipt') }}</a>
        @endcan
    </x-page-head>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Invoice / customer…')" class="w-full sm:w-72" />
        <x-select-field name="client_id" :label="__('Customer')" :value="request('client_id')" :placeholder="__('All customers')"
            :options="$clients" class="w-full sm:w-56" />
    </x-auto-filter>

    <div id="list-results" class="voucher-settle card overflow-hidden" x-data="{ openId: {{ (int) old('sale_id', 0) }} }">
        <div class="table-wrap border-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th class="num">{{ __('Bill') }}</th>
                        <th class="num">{{ __('Due') }}</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dues as $sale)
                        <tr class="align-top" :class="openId === {{ $sale->id }} ? 'bg-sage/30' : ''">
                            <td>
                                <a href="{{ route('sales.show', $sale) }}" class="font-semibold text-leaf hover:underline">{{ $sale->invoice_no }}</a>
                            </td>
                            <td>
                                <span class="font-medium">{{ $sale->customer_name ?: $sale->client?->display_name }}</span>
                                @if ($sale->customer_phone ?: $sale->client?->phone)
                                    <span class="block text-xs text-ink-soft">{{ $sale->customer_phone ?: $sale->client?->phone }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ fmt_date($sale->invoice_date) }}</td>
                            <td class="num">{{ money($sale->grand_total) }}</td>
                            <td class="num font-semibold text-accent">{{ money($sale->balance) }}</td>
                            <td class="no-print text-right">
                                @can('menu', ['receipts', 'add'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            @click="openId = openId === {{ $sale->id }} ? 0 : {{ $sale->id }}">
                                        <span x-text="openId === {{ $sale->id }} ? '{{ __('Close') }}' : '{{ __('Receive') }}'"></span>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                        @can('menu', ['receipts', 'add'])
                            <tr x-show="openId === {{ $sale->id }}" x-cloak class="bg-sage/20">
                                <td colspan="6" class="!p-3">
                                    <form method="POST" action="{{ route('receipts.store') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                        @csrf
                                        <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                                        <x-field name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('sale_id') == $sale->id ? old('amount', $sale->balance) : $sale->balance" required />
                                        @include('partials.payment-ledger-field', ['label' => __('Received in')])
                                        <x-field name="payment_date" type="date" :label="__('Date')" :value="old('payment_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
                                        <x-field name="reference" :label="__('Reference')" :value="old('reference')" />
                                        <div class="flex items-end">
                                            <button class="btn-primary w-full">{{ __('Save receipt') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-soft">{{ __('No open customer dues.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-sage px-4 py-3">{{ $dues->links() }}</div>
    </div>
</x-app-layout>
