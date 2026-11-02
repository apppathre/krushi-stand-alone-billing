<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Outstanding / Ageing') }}</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <a href="{{ route('reports.outstanding', ['view' => 'party', 'side' => $side === 'payable' ? 'payable' : null]) }}" class="btn-secondary btn-sm">{{ __('Party summary') }}</a>
        <span class="btn-primary btn-sm">{{ __('Bill-wise ageing') }}</span>
    </div>

    <x-auto-filter>
        <x-select-field name="side" :label="__('Type')" :value="$side"
            :options="['receivable' => __('Receivable (Customers)'), 'payable' => __('Payable (Suppliers)')]" class="w-full sm:w-56" />
        <x-field name="as_of" type="date" :label="__('As of')" :value="$as_of" class="w-full sm:w-40" />
        <x-field name="interest_pct" type="number" step="0.01" min="0" :label="__('Interest % p.a.')"
                 :value="old('interest_pct', $interest_pct)" class="w-full sm:w-36"
                 :hint="__('Optional. Simple interest on overdue days only.')" />
        <input type="hidden" name="view" value="ageing">
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results" class="space-y-4">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                'current' => __('Not due'),
                'b1_30' => '1–30',
                'b31_60' => '31–60',
                'b61_90' => '61–90',
                'b90_plus' => '90+',
            ] as $key => $label)
                <div class="card card-body py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-ink-soft">{{ $label }}</p>
                    <p class="font-display text-lg font-semibold text-leaf-dark">&#8377; {{ money($buckets[$key] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sage bg-sage/40 px-5 py-3">
                <h2 class="card-title">{{ $side === 'payable' ? __('Payable bills') : __('Receivable bills') }}</h2>
                <div class="text-sm">
                    <span class="font-semibold">{{ __('Total') }}: &#8377; {{ money($totals['balance']) }}</span>
                    @if ($interest_pct > 0)
                        <span class="ml-3 text-accent">{{ __('Interest') }}: &#8377; {{ money($totals['interest']) }}</span>
                    @endif
                </div>
            </div>
            <div class="table-wrap border-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Bill') }}</th>
                            <th>{{ __('Party') }}</th>
                            <th>{{ __('Bill date') }}</th>
                            <th>{{ __('Due date') }}</th>
                            <th class="num">{{ __('Overdue') }}</th>
                            <th class="num">{{ __('Due') }}</th>
                            @if ($interest_pct > 0)
                                <th class="num">{{ __('Interest') }}</th>
                                <th class="no-print"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td class="font-semibold">
                                    @if ($side === 'receivable')
                                        <a class="text-leaf hover:underline" href="{{ route('sales.show', $r->id) }}">{{ $r->doc_no }}</a>
                                    @else
                                        <a class="text-leaf hover:underline" href="{{ route('purchases.show', $r->id) }}">{{ $r->doc_no }}</a>
                                    @endif
                                </td>
                                <td>
                                    <a class="hover:underline" href="{{ route('reports.party-ledger', ['ledger_id' => $r->party_id]) }}">{{ $r->party_name }}</a>
                                    @if ($r->phone)<span class="block text-xs text-ink-soft">{{ $r->phone }}</span>@endif
                                </td>
                                <td>{{ fmt_date($r->bill_date) }}</td>
                                <td>{{ fmt_date($r->due_date) }}</td>
                                <td class="num">{{ $r->overdue_days > 0 ? $r->overdue_days.' '.__('days') : '—' }}</td>
                                <td class="num font-semibold">{{ money($r->balance) }}</td>
                                @if ($interest_pct > 0)
                                    <td class="num">{{ money($r->interest) }}</td>
                                    <td class="no-print">
                                        @if ($side === 'receivable' && $r->interest > 0.004 && ! empty($incomeLedgers))
                                            @php
                                                $interestLedgerId = collect($incomeLedgers)->search(fn ($label) => str_contains(mb_strtolower($label), 'interest'))
                                                    ?: array_key_first($incomeLedgers);
                                            @endphp
                                            <form method="POST" action="{{ route('reports.outstanding.interest') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="party_ledger_id" value="{{ $r->party_id }}">
                                                <input type="hidden" name="income_ledger_id" value="{{ $interestLedgerId }}">
                                                <input type="hidden" name="amount" value="{{ $r->interest }}">
                                                <input type="hidden" name="voucher_date" value="{{ $as_of }}">
                                                <input type="hidden" name="narration" value="{{ __('Interest on :no (:days days @ :pct% p.a.)', ['no' => $r->doc_no, 'days' => $r->overdue_days, 'pct' => $interest_pct]) }}">
                                                <button class="btn-secondary btn-sm" data-busy-label="{{ __('Posting…') }}">{{ __('Apply') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $interest_pct > 0 ? 8 : 6 }}" class="py-8 text-center text-ink-soft">{{ __('No open bills.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($interest_pct > 0 && $side === 'receivable' && count($incomeLedgers) > 1)
            <p class="text-xs text-ink-soft no-print">{{ __('Apply posts a journal voucher (Dr customer, Cr first income ledger). Change income ledgers under Ledger Master if needed.') }}</p>
        @endif
    </div>
</x-app-layout>
