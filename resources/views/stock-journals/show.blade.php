@php
    $menuKey = $menuKey ?? \App\Http\Controllers\StockJournalController::menuKey($journal->type);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $title }} {{ $journal->journal_no }}</h1>
            <p class="text-xs text-ink-soft">
                {{ fmt_date($journal->journal_date) }}
                · {{ $journal->godown?->display_name }}
                @if ($journal->type === 'transfer' && $journal->toGodown)
                    → {{ $journal->toGodown->display_name }}
                @endif
                · {{ __(ucfirst($journal->status)) }}
            </p>
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-3 rounded-lg border border-leaf/30 bg-leaf/10 px-3 py-2 text-sm no-print">{{ session('status') }}</div>
    @endif

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <a href="{{ route('stock-journals.'.$journal->type) }}" class="btn-secondary btn-sm">{{ __('Back') }}</a>
        <button type="button" onclick="window.print()" class="btn-secondary btn-sm">{{ __('Print') }}</button>
        @if ($journal->status === 'active')
            @if (auth()->user()->canMenu($menuKey, 'edit'))
                <a href="{{ route('stock-journals.edit', $journal) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
            @endif
            @if (auth()->user()->canMenu($menuKey, 'delete'))
                <form method="POST" action="{{ route('stock-journals.cancel', $journal) }}"
                      onsubmit="return confirm('{{ __('Cancel this voucher and reverse stock?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn-danger btn-sm">{{ __('Cancel / Delete') }}</button>
                </form>
            @endif
        @endif
    </div>

    <div class="print-only mb-3 hidden print:block">
        <h2 class="text-lg font-bold">{{ $title }} {{ $journal->journal_no }}</h2>
        <p class="text-sm">{{ fmt_date($journal->journal_date) }}
            · {{ $journal->godown?->display_name }}
            @if ($journal->toGodown) → {{ $journal->toGodown->display_name }} @endif
        </p>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Side') }}</th>
                    <th>{{ __('Place') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Batch') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    @if ($journal->type === 'physical')
                        <th class="num">{{ __('Counted') }}</th>
                        <th class="num">{{ __('System') }}</th>
                    @endif
                    <th class="num">{{ __('Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($journal->lines as $line)
                    <tr>
                        <td>{{ __(ucfirst($line->side)) }}</td>
                        <td>{{ $line->godown?->display_name ?? $journal->godown?->display_name }}</td>
                        <td>{{ $line->product?->display_name }}</td>
                        <td>{{ $line->batch?->batch_no ?? $line->batch_no }}</td>
                        <td class="num">{{ qty($line->qty) }}</td>
                        @if ($journal->type === 'physical')
                            <td class="num">{{ $line->counted_qty !== null ? qty($line->counted_qty) : '—' }}</td>
                            <td class="num">{{ $line->system_qty !== null ? qty($line->system_qty) : '—' }}</td>
                        @endif
                        <td class="num">{{ money($line->rate) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($journal->notes)
        <p class="mt-3 text-sm text-ink-soft">{{ $journal->notes }}</p>
    @endif
</x-app-layout>
