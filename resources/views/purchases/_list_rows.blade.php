<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('Purchase no') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Supplier') }}</th>
                <th>{{ __('Supplier invoice') }}</th>
                <th>{{ __('Godown') }}</th>
                <th class="num">{{ __('Total') }}</th>
                <th class="num">{{ __('Paid') }}</th>
                <th class="num">{{ __('Due') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="num">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $p)
                <tr>
                    <td><a href="{{ route('purchases.show', $p) }}" class="font-semibold text-leaf hover:underline">{{ $p->purchase_no }}</a></td>
                    <td>{{ fmt_date($p->purchase_date) }}</td>
                    <td>{{ $p->supplier?->display_name }}</td>
                    <td>{{ $p->supplier_invoice_no }}</td>
                    <td>{{ $p->godown?->display_name }}</td>
                    <td class="num">{{ money($p->grand_total) }}</td>
                    <td class="num">{{ money($p->paid_amount) }}</td>
                    <td class="num {{ $p->balance > 0 && ! $p->isCancelled() ? 'font-semibold text-accent' : '' }}">{{ money($p->balance) }}</td>
                    <td>
                        @if ($p->isCancelled()) <span class="badge-gray">{{ __('Cancelled') }}</span>
                        @elseif ($p->balance <= 0.004) <span class="badge-green">{{ __('Paid') }}</span>
                        @elseif ($p->paid_amount > 0) <span class="badge-amber">{{ __('Partial') }}</span>
                        @else <span class="badge-red">{{ __('Unpaid') }}</span> @endif
                    </td>
                    <td class="num whitespace-nowrap">
                        <a href="{{ route('purchases.show', $p) }}" class="btn-secondary btn-sm">{{ __('View') }}</a>
                        @can('menu', ['purchases', 'delete'])
                            @unless ($p->isCancelled())
                                <button type="button" class="btn-danger btn-sm"
                                        data-confirm-submit
                                        data-confirm-message="{{ __('Cancel this purchase? Stock and ledger entries will be reversed.') }}"
                                        data-confirm-action="{{ route('purchases.cancel', $p) }}"
                                        data-confirm-method="DELETE">
                                    {{ __('Cancel') }}
                                </button>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $rows->withQueryString()->links() }}</div>
