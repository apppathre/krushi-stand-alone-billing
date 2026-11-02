<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Trial Balance') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="as_of" type="date" :label="__('As of')" :value="$as_of" class="w-full sm:w-40" />
        <x-slot:actions>
            <x-report-export :formats="['print']" />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results" class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sage bg-sage/40 px-5 py-3">
            <h2 class="card-title">{{ __('Trial Balance') }} · {{ fmt_date($as_of) }}</h2>
            <div class="text-sm font-semibold">
                Dr &#8377; {{ money($total_dr) }}
                <span class="mx-2 text-ink-soft">|</span>
                Cr &#8377; {{ money($total_cr) }}
                @if (abs($total_dr - $total_cr) > 0.05)
                    <span class="ml-2 text-accent">{{ __('Difference') }}: {{ money(abs($total_dr - $total_cr)) }}</span>
                @endif
            </div>
        </div>
        <div class="table-wrap border-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Ledger') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="num">{{ __('Closing Dr') }}</th>
                        <th class="num">{{ __('Closing Cr') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <a class="font-semibold text-leaf hover:underline" href="{{ route('reports.party-ledger', ['ledger_id' => $r->id]) }}">{{ $r->name }}</a>
                            </td>
                            <td class="text-xs text-ink-soft">{{ $r->family === 'party' ? __('Party') : ucfirst($r->type) }}</td>
                            <td class="num">{{ $r->closing_dr > 0 ? money($r->closing_dr) : '' }}</td>
                            <td class="num">{{ $r->closing_cr > 0 ? money($r->closing_cr) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-ink-soft">{{ __('No balances.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="2">{{ __('Total') }}</td>
                        <td class="num">{{ money($total_dr) }}</td>
                        <td class="num">{{ money($total_cr) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-app-layout>
