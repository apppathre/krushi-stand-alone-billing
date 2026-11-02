<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('Invoice') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Client') }}</th>
                <th>{{ __('Godown') }}</th>
                <th class="num">{{ __('Total') }}</th>
                <th class="num">{{ __('Paid') }}</th>
                <th class="num">{{ __('Due') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="num">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $s)
                <tr>
                    <td><a href="{{ route('sales.show', $s) }}" class="font-semibold text-leaf hover:underline">{{ $s->invoice_no }}</a></td>
                    <td>{{ fmt_date($s->invoice_date) }}</td>
                    <td>{{ $s->customer_name ?: $s->client?->display_name }}</td>
                    <td>{{ $s->godown?->display_name }}</td>
                    <td class="num">{{ money($s->grand_total) }}</td>
                    <td class="num">{{ money($s->paid_amount) }}</td>
                    <td class="num {{ $s->balance > 0 && ! $s->isCancelled() ? 'font-semibold text-accent' : '' }}">{{ money($s->balance) }}</td>
                    <td><x-sale-status :sale="$s" /></td>
                    <td class="num whitespace-nowrap">
                        <a href="{{ route('sales.show', $s) }}" class="btn-secondary btn-sm">{{ __('View') }}</a>
                        <a href="{{ route('sales.pdf', $s) }}" target="_blank" class="btn-secondary btn-sm">PDF</a>
                        @can('menu', ['sales', 'delete'])
                            @unless ($s->isCancelled())
                                <button type="button" class="btn-danger btn-sm"
                                        data-confirm-submit
                                        data-confirm-message="{{ __('Cancel this invoice? Stock and ledger entries will be reversed.') }}"
                                        data-confirm-action="{{ route('sales.cancel', $s) }}"
                                        data-confirm-method="DELETE">
                                    {{ __('Cancel') }}
                                </button>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rows->withQueryString()->links() }}</div>
