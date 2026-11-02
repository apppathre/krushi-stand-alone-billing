<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Day Book') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="date" type="date" :label="__('Date')" :value="$date" class="w-44" />
        <x-select-field name="godown_id" :label="__('Place')" :value="request('godown_id')" :placeholder="__('All')"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-44" />
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="mb-4 grid gap-4 sm:grid-cols-2">
            @foreach ($book['position'] as $pos)
                <div class="stat-card">
                    <p class="stat-label">{{ $pos['ledger']->display_name }}</p>
                    <p class="mt-1 text-sm">{{ __('Opening') }}: <strong>{{ money($pos['opening']) }}</strong> &middot; {{ __('In') }}: <strong class="text-leaf">{{ money($pos['in']) }}</strong>
                        &middot; {{ __('Out') }}: <strong class="text-accent">{{ money($pos['out']) }}</strong></p>
                    <p class="stat-value">{{ money($pos['closing']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>{{ __('Voucher') }}</th><th>{{ __('Ledger') }}</th><th>{{ __('Narration') }}</th><th class="num">{{ __('Debit') }}</th><th class="num">{{ __('Credit') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($book['vouchers'] as $entries)
                        @foreach ($entries as $e)
                            <tr class="{{ $loop->first ? 'border-t-2 border-sage-dark' : '' }}">
                                <td>@if ($loop->first)<strong>{{ $e->voucher_no }}</strong><span class="block text-xs text-ink-soft">{{ __(ucwords(str_replace('_', ' ', $e->voucher_type))) }}</span>@endif</td>
                                <td class="{{ $e->credit > 0 ? 'pl-8' : '' }}">{{ $e->ledger->display_name }}</td>
                                <td>{{ $e->narration }}</td>
                                <td class="num">{{ $e->debit > 0 ? money($e->debit) : '' }}</td>
                                <td class="num">{{ $e->credit > 0 ? money($e->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-soft">{{ __('No transactions on this day.') }}</td></tr>
                    @endforelse
                    <tr class="bg-sage/50 font-bold"><td colspan="3">{{ __('Total') }}</td><td class="num">{{ money($book['debit']) }}</td><td class="num">{{ money($book['credit']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
