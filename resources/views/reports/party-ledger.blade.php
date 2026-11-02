<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title flex items-center gap-2"><x-icon name="ledger" class="h-7 w-7 text-leaf" /> {{ __('Ledger Report') }}</h1>
    </x-slot>

    <x-auto-filter>
        <div class="w-72">
            <label class="label" for="ledger_id">{{ __('Ledger') }}</label>
            <select id="ledger_id" name="ledger_id" class="input">
                <option value="">{{ __('Select ledger') }}</option>
                @foreach ($ledgers->groupBy('family') as $family => $group)
                    <optgroup label="{{ $family === 'party' ? __('Parties') : __('Account Books') }}">
                        @foreach ($group as $l)
                            <option value="{{ $l->id }}" @selected(request('ledger_id') == $l->id)>{{ $l->display_name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-40" />
        <x-slot:actions>
            <x-report-export :formats="['print']" />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        @if (! $ledger)
            <div class="card card-body text-sm text-ink-soft">{{ __('Select a ledger to view its statement.') }}</div>
        @else
            <div class="card card-body mb-4">
                <h2 class="card-title">{{ $ledger->display_name }}</h2>
                <p class="text-sm text-ink-soft">{{ fmt_date($from) }} &rarr; {{ fmt_date($to) }}
                    @if ($ledger->phone) &middot; {{ $ledger->phone }} @endif</p>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Narration') }}</th>
                            <th class="num">{{ __('Debit') }}</th><th class="num">{{ __('Credit') }}</th><th class="num">{{ __('Balance') }}</th></tr>
                    </thead>
                    <tbody>
                        <tr class="bg-sage/50 font-semibold">
                            <td colspan="5">{{ __('Opening balance') }}</td>
                            <td class="num">{{ money(abs($statement['opening'])) }} {{ $statement['opening'] >= 0 ? __('Dr') : __('Cr') }}</td>
                        </tr>
                        @foreach ($statement['rows'] as $e)
                            <tr>
                                <td>{{ fmt_date($e->entry_date) }}</td>
                                <td>{{ $e->voucher_no }} <span class="text-xs text-ink-soft">{{ __(ucwords(str_replace('_', ' ', $e->voucher_type))) }}</span></td>
                                <td>{{ $e->narration }}</td>
                                <td class="num">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td class="num">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                                <td class="num">{{ money(abs($e->running)) }} {{ $e->running >= 0 ? __('Dr') : __('Cr') }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-sage/50 font-bold">
                            <td colspan="3">{{ __('Total / Closing balance') }}</td>
                            <td class="num">{{ money($statement['debit']) }}</td>
                            <td class="num">{{ money($statement['credit']) }}</td>
                            <td class="num">{{ money(abs($statement['closing'])) }} {{ $statement['closing'] >= 0 ? __('Dr') : __('Cr') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
