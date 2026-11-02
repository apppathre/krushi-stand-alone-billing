<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('New credit note') }}</h1>
        <p class="text-xs text-ink-soft">{{ __('Step 1: choose the invoice to return goods against.') }}</p>
    </x-slot>

    <x-auto-filter>
        <x-field name="q" :label="__('Search invoice')" :value="request('q')" :placeholder="__('Invoice no, client, phone…')" class="w-full max-w-md" />
    </x-auto-filter>

    <div id="list-results">
        <div class="txn-pick-list">
            @forelse ($sales as $s)
                <a href="{{ route('sale-returns.create', ['sale_id' => $s->id]) }}" class="txn-pick-card">
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $s->invoice_no }}</p>
                        <p class="text-xs text-ink-soft truncate">{{ fmt_date($s->invoice_date) }} · {{ $s->customer_name ?: $s->client->display_name }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold">{{ money($s->grand_total) }}</p>
                        <span class="text-xs font-semibold text-leaf-dark">{{ __('Select') }} →</span>
                    </div>
                </a>
            @empty
                <p class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</p>
            @endforelse
        </div>

        <div class="table-wrap hidden lg:block">
            <table class="table">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Client') }}</th><th class="num">{{ __('Total') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sales as $s)
                        <tr>
                            <td class="font-semibold">{{ $s->invoice_no }}</td>
                            <td>{{ fmt_date($s->invoice_date) }}</td>
                            <td>{{ $s->customer_name ?: $s->client->display_name }}</td>
                            <td class="num">{{ money($s->grand_total) }}</td>
                            <td class="num"><a href="{{ route('sale-returns.create', ['sale_id' => $s->id]) }}" class="btn-primary btn-sm">{{ __('Select') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sales->links() }}</div>
    </div>
</x-app-layout>
