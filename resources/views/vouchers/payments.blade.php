<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Payment') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Bill payments')" :blurb="__('Pay against open purchase bills. For rent / salary / charges use Payment voucher.')">
        @can('menu', ['account_payments', 'view'])
            <a href="{{ route('account-vouchers.payment') }}" class="btn-secondary btn-sm">{{ __('Payment voucher') }}</a>
        @endcan
        @can('menu', ['bulk_payment', 'view'])
            <a href="{{ route('bulk-payments.create') }}" class="btn-secondary btn-sm">{{ __('Bulk payment') }}</a>
        @endcan
    </x-page-head>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Bill / supplier…')" class="w-full sm:w-72" />
        <x-select-field name="supplier_id" :label="__('Supplier')" :value="request('supplier_id')" :placeholder="__('All suppliers')"
            :options="$suppliers" class="w-full sm:w-56" />
    </x-auto-filter>

    <div id="list-results" class="voucher-settle card overflow-hidden" x-data="{ openId: {{ (int) old('purchase_id', 0) }} }">
        <div class="table-wrap border-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Bill') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th class="num">{{ __('Bill') }}</th>
                        <th class="num">{{ __('Due') }}</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dues as $purchase)
                        <tr class="align-top" :class="openId === {{ $purchase->id }} ? 'bg-sage/30' : ''">
                            <td>
                                <a href="{{ route('purchases.show', $purchase) }}" class="font-semibold text-leaf hover:underline">{{ $purchase->purchase_no }}</a>
                                @if ($purchase->supplier_invoice_no)
                                    <span class="block text-xs text-ink-soft">{{ $purchase->supplier_invoice_no }}</span>
                                @endif
                            </td>
                            <td class="font-medium">{{ $purchase->supplier?->display_name }}</td>
                            <td class="whitespace-nowrap">{{ fmt_date($purchase->purchase_date) }}</td>
                            <td class="num">{{ money($purchase->grand_total) }}</td>
                            <td class="num font-semibold text-accent">{{ money($purchase->balance) }}</td>
                            <td class="no-print text-right">
                                @can('menu', ['payments', 'add'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            @click="openId = openId === {{ $purchase->id }} ? 0 : {{ $purchase->id }}">
                                        <span x-text="openId === {{ $purchase->id }} ? '{{ __('Close') }}' : '{{ __('Pay') }}'"></span>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                        @can('menu', ['payments', 'add'])
                            <tr x-show="openId === {{ $purchase->id }}" x-cloak class="bg-sage/20">
                                <td colspan="6" class="!p-3">
                                    <form method="POST" action="{{ route('payments.store') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                        @csrf
                                        <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
                                        <x-field name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('purchase_id') == $purchase->id ? old('amount', $purchase->balance) : $purchase->balance" required />
                                        @include('partials.payment-ledger-field', ['label' => __('Paid from')])
                                        <x-field name="payment_date" type="date" :label="__('Date')" :value="old('payment_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
                                        <x-field name="reference" :label="__('Reference')" :value="old('reference')" />
                                        <div class="flex items-end">
                                            <button class="btn-primary w-full">{{ __('Save payment') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-soft">{{ __('No open supplier dues.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-sage px-4 py-3">{{ $dues->links() }}</div>
    </div>
</x-app-layout>
