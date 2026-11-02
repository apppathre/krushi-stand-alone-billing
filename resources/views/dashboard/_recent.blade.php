@if ($recentSales->isEmpty())
    <p class="px-5 py-6 text-sm text-ink-soft">{{ __('No invoices yet.') }}</p>
@else
    <div class="table-wrap border-0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Invoice') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Client') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentSales as $s)
                    <tr>
                        <td>
                            @can('menu', ['sales', 'view'])
                                <a class="font-semibold text-leaf hover:underline" href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a>
                            @else
                                {{ $s->invoice_no }}
                            @endcan
                        </td>
                        <td>{{ fmt_date($s->invoice_date) }}</td>
                        <td>{{ $s->customer_name ?: $s->client->display_name }}</td>
                        <td class="num">{{ money($s->grand_total) }}</td>
                        <td><x-sale-status :sale="$s" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
