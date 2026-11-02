@php $menuKey = $menuKey ?? \App\Http\Controllers\StockJournalController::menuKey($type ?? 'journal'); @endphp
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Place') }}</th>
                <th>{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="font-semibold">{{ $row->journal_no }}</td>
                    <td>{{ fmt_date($row->journal_date) }}</td>
                    <td>
                        {{ $row->godown?->display_name }}
                        @if ($row->type === 'transfer' && $row->toGodown)
                            → {{ $row->toGodown->display_name }}
                        @endif
                    </td>
                    <td><span class="{{ $row->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ __(ucfirst($row->status)) }}</span></td>
                    <td class="num whitespace-nowrap">
                        <a href="{{ route('stock-journals.show', $row) }}" class="btn-secondary btn-sm">{{ __('Open') }}</a>
                        @if ($row->status === 'active' && auth()->user()->canMenu($menuKey, 'edit'))
                            <a href="{{ route('stock-journals.edit', $row) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-ink-soft">{{ __('No vouchers.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-2">{{ $rows->links() }}</div>
