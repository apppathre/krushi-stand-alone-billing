<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Cash / Bank book') }}</h1>
    </x-slot>

    <x-auto-filter>
        <div class="w-full sm:w-72">
            <label class="label" for="ledger_id">{{ __('Cash / Bank / UPI') }}</label>
            <select id="ledger_id" name="ledger_id" class="input js-searchable" data-placeholder="{{ __('Select account') }}">
                @foreach ($instruments as $l)
                    <option value="{{ $l->id }}" @selected(optional($ledger)->id === $l->id)>{{ $l->display_name }} · {{ $l->type === 'cash' ? __('Cash') : __('Bank / UPI') }}</option>
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
            <div class="card card-body text-sm text-ink-soft">{{ __('Add a cash or bank ledger under Ledger Master.') }}</div>
        @else
            <div class="card card-body mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="card-title">{{ $ledger->display_name }}</h2>
                    <p class="text-sm text-ink-soft">{{ fmt_date($from) }} → {{ fmt_date($to) }}</p>
                </div>
                <div class="text-right text-sm">
                    <p>{{ __('Opening') }}: <strong>{{ money(abs($statement['opening'])) }} {{ $statement['opening'] < 0 ? 'Cr' : 'Dr' }}</strong></p>
                    <p>{{ __('Closing') }}: <strong>{{ money(abs($statement['closing'])) }} {{ $statement['closing'] < 0 ? 'Cr' : 'Dr' }}</strong></p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Voucher') }}</th>
                            <th>{{ __('Narration') }}</th>
                            <th class="num">{{ __('In') }}</th>
                            <th class="num">{{ __('Out') }}</th>
                            <th class="num">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-sage/30">
                            <td colspan="3" class="font-semibold">{{ __('Opening balance') }}</td>
                            <td class="num"></td><td class="num"></td>
                            <td class="num font-semibold">{{ money(abs($statement['opening'])) }} {{ $statement['opening'] < 0 ? 'Cr' : 'Dr' }}</td>
                        </tr>
                        @forelse ($statement['rows'] as $e)
                            <tr>
                                <td>{{ fmt_date($e->entry_date) }}</td>
                                <td class="text-xs">{{ $e->voucher_no ?: ($e->voucher_type.'#'.$e->voucher_id) }}</td>
                                <td>{{ $e->narration }}</td>
                                <td class="num">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td class="num">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                                <td class="num">{{ money(abs($e->running)) }} {{ $e->running < 0 ? 'Cr' : 'Dr' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-ink-soft">{{ __('No entries in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
