<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Balance Sheet') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="as_of" type="date" :label="__('As of')" :value="$as_of" class="w-full sm:w-40" />
        <x-slot:actions>
            <x-report-export :formats="['print']" />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results" class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-sage bg-sage/40 px-5 py-3">
                <h2 class="card-title">{{ __('Assets') }}</h2>
            </div>
            <div class="card-body space-y-2 text-sm">
                @foreach ($assets as $row)
                    <div class="flex justify-between gap-3">
                        <span>{{ $row['name'] }}</span>
                        <span class="font-semibold">&#8377; {{ money($row['amount']) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between border-t border-sage pt-2 font-display text-base font-semibold text-leaf-dark">
                    <span>{{ __('Total assets') }}</span>
                    <span>&#8377; {{ money($asset_total) }}</span>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-sage bg-sage/40 px-5 py-3">
                <h2 class="card-title">{{ __('Liabilities & capital') }}</h2>
            </div>
            <div class="card-body space-y-2 text-sm">
                @foreach ($liabilities as $row)
                    <div class="flex justify-between gap-3">
                        <span>{{ $row['name'] }}</span>
                        <span class="font-semibold">&#8377; {{ money($row['amount']) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between border-t border-sage pt-2 font-display text-base font-semibold text-leaf-dark">
                    <span>{{ __('Total') }}</span>
                    <span>&#8377; {{ money($liability_total) }}</span>
                </div>
            </div>
        </div>

        @if (abs($difference) > 0.5)
            <div class="card card-body text-sm text-accent lg:col-span-2">
                {{ __('Sheet difference') }}: &#8377; {{ money($difference) }}.
                {{ __('Check openings, stock value, and unmapped ledgers. This is a simple shop sheet, not a CA schedule.') }}
            </div>
        @endif
    </div>
</x-app-layout>
