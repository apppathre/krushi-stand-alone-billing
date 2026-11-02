<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Supplier') }}</th>
                <th class="num">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="font-semibold">{{ $row->order_no }}</td>
                    <td>{{ fmt_date($row->order_date) }}</td>
                    <td>{{ $row->supplier?->display_name }}</td>
                    <td class="num">{{ money($row->grand_total) }}</td>
                    <td><span class="{{ $row->status === 'open' ? 'badge-green' : ($row->status === 'converted' ? 'badge-amber' : 'badge-gray') }}">{{ __(ucfirst($row->status)) }}</span></td>
                    <td class="num whitespace-nowrap">
                        <a href="{{ route('purchase-orders.show', $row) }}" class="btn-secondary btn-sm">{{ __('Open') }}</a>
                        @if ($row->isOpen() && auth()->user()->canMenu('purchase_orders', 'edit'))
                            <a href="{{ route('purchase-orders.edit', $row) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-ink-soft">{{ __('No purchase orders.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-2">{{ $rows->links() }}</div>
