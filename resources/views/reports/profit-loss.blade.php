<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Profit & Loss') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-40" />
        <x-select-field name="godown_id" :label="__('Godown')" :value="request('godown_id')" :placeholder="__('All')"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-44" />
        <x-slot:actions>
            <x-report-export :formats="['print']" />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Trading account --}}
            <div class="card">
                <div class="border-b border-sage px-5 py-3"><h2 class="card-title">{{ __('Trading Account') }}</h2></div>
                <table class="table">
                    <tbody>
                        @foreach ($pl['sales'] as $row)
                            <tr><td>{{ __('Sales') }}: {{ $row['name'] }}</td><td class="num">{{ money($row['amount']) }}</td></tr>
                        @endforeach
                        <tr class="font-semibold"><td>{{ __('Net sales') }}</td><td class="num">{{ money($pl['sales_total']) }}</td></tr>
                        <tr><td>{{ __('Opening stock') }}</td><td class="num">{{ money($pl['opening_stock']) }}</td></tr>
                        <tr><td>{{ __('Add: Purchases') }}</td><td class="num">{{ money($pl['purchase_total']) }}</td></tr>
                        <tr><td>{{ __('Less: Closing stock') }}</td><td class="num">({{ money($pl['closing_stock']) }})</td></tr>
                        <tr class="font-semibold"><td>{{ __('Cost of goods sold') }}</td><td class="num">{{ money($pl['cogs']) }}</td></tr>
                        <tr class="bg-sage/50 font-bold {{ $pl['gross_profit'] < 0 ? 'text-red-700' : 'text-leaf-dark' }}">
                            <td>{{ $pl['gross_profit'] >= 0 ? __('Gross profit') : __('Gross loss') }}</td><td class="num">{{ money(abs($pl['gross_profit'])) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Profit & loss --}}
            <div class="card">
                <div class="border-b border-sage px-5 py-3"><h2 class="card-title">{{ __('Profit & Loss Account') }}</h2></div>
                <table class="table">
                    <tbody>
                        <tr class="font-semibold"><td>{{ $pl['gross_profit'] >= 0 ? __('Gross profit') : __('Gross loss') }}</td><td class="num">{{ money($pl['gross_profit']) }}</td></tr>
                        @foreach ($pl['incomes'] as $row)
                            <tr><td>{{ __('Add: :name', ['name' => $row['name']]) }}</td><td class="num">{{ money($row['amount']) }}</td></tr>
                        @endforeach
                        @foreach ($pl['expenses'] as $row)
                            <tr><td>{{ __('Less: :name', ['name' => $row['name']]) }}</td><td class="num">({{ money($row['amount']) }})</td></tr>
                        @endforeach
                        @if ($pl['incomes']->isEmpty() && $pl['expenses']->isEmpty())
                            <tr><td colspan="2" class="text-sm text-ink-soft">{{ __('No other income or expenses posted in this period.') }}</td></tr>
                        @endif
                        <tr class="bg-sage/50 text-base font-bold {{ $pl['net_profit'] < 0 ? 'text-red-700' : 'text-leaf-dark' }}">
                            <td>{{ $pl['net_profit'] >= 0 ? __('Net profit') : __('Net loss') }}</td><td class="num">&#8377; {{ money(abs($pl['net_profit'])) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-3 text-xs text-ink-soft">{{ __('Stock is valued at cost from the stock movement log. Sales are net of returns; discounts and round-off appear under other income/expenses.') }}</p>
    </div>
</x-app-layout>
