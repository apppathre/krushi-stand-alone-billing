<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title flex items-center gap-2">
                <x-icon :name="$kind === 'receipt' ? 'receipt' : 'payment'" class="h-7 w-7 text-leaf" />
                {{ $title }}
            </h1>
            <p class="text-xs text-ink-soft">{{ $blurb }}</p>
        </div>
    </x-slot>

    <div class="mb-3 flex flex-wrap justify-end gap-2">
        @if ($kind === 'receipt')
            <a href="{{ route('receipts.index') }}" class="btn-secondary btn-sm">{{ __('Single receipt') }}</a>
        @else
            <a href="{{ route('payments.index') }}" class="btn-secondary btn-sm">{{ __('Single payment') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ $filterRoute }}" class="card card-body mb-4 grid gap-3 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label class="label" for="f-party_id">{{ $partyLabel }} <span class="text-accent">*</span></label>
            <select id="f-party_id" name="party_id" class="input" required onchange="this.form.submit()">
                <option value="">{{ __('Select…') }}</option>
                @foreach ($parties as $p)
                    <option value="{{ $p->id }}" @selected((int) $partyId === (int) $p->id)>
                        {{ $p->display_name }}{{ $p->phone ? ' · '.$p->phone : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button class="btn-secondary w-full">{{ __('Load dues') }}</button>
        </div>
    </form>

    @if (! $partyId)
        <div class="card card-body text-center text-ink-soft">{{ __('Pick a party to see open bills for bulk settle.') }}</div>
    @elseif ($dues->isEmpty())
        <div class="card card-body text-center text-ink-soft">{{ __('No open dues for this party.') }}</div>
    @else
        <form method="POST" action="{{ $storeRoute }}" class="space-y-4" data-bulk-settle data-busy-label="{{ __('Settling…') }}">
            @csrf
            <input type="hidden" name="party_id" value="{{ $partyId }}">

            <div class="card card-body grid gap-4 md:grid-cols-4">
                <x-field name="payment_date" type="date" :label="__('Date')"
                         :value="old('payment_date', $voucherDate ?? now()->toDateString())"
                         :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                         :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()"
                         required />
                @include('partials.payment-ledger-field', [
                    'label' => $kind === 'receipt' ? __('Received in') : __('Paid from'),
                ])
                <div>
                    <label class="label" for="f-lump">{{ $kind === 'receipt' ? __('Amount received') : __('Amount paid') }} <span class="text-accent">*</span></label>
                    <input id="f-lump" type="number" step="0.01" min="0.01" name="amount" class="input"
                           value="{{ old('amount') }}" required inputmode="decimal" data-bulk-lump>
                    @error('amount') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <x-field name="reference" :label="__('Reference / batch')" :value="old('reference')"
                         :hint="__('Shared on every bill line in ledger')" />
            </div>

            <div class="rounded-lg border border-sage/60 bg-cream/40 px-3 py-2 text-sm" data-bulk-sum aria-live="polite">
                {{ __('Tick bills, enter the lump amount — allocation fills oldest-first.') }}
            </div>

            <div class="table-wrap">
                <table class="table" data-bulk-table>
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" data-bulk-pick-all title="{{ __('Select all') }}">
                            </th>
                            <th>{{ $billLabel }}</th>
                            <th>{{ __('Date') }}</th>
                            <th class="num">{{ __('Bill') }}</th>
                            <th class="num">{{ __('Paid') }}</th>
                            <th class="num">{{ __('Due') }}</th>
                            <th class="num">{{ __('Allocate') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dues as $bill)
                            @php
                                $date = $kind === 'receipt' ? $bill->invoice_date : $bill->purchase_date;
                                $number = $kind === 'receipt' ? $bill->invoice_no : $bill->purchase_no;
                                $due = (float) $bill->balance;
                            @endphp
                            <tr data-bulk-row data-balance="{{ $due }}" data-number="{{ $number }}">
                                <td>
                                    <input type="checkbox" name="bill_ids[]" value="{{ $bill->id }}" data-bulk-bill
                                           @checked(collect(old('bill_ids', []))->contains($bill->id))>
                                </td>
                                <td class="font-semibold">
                                    @if ($kind === 'receipt')
                                        <a href="{{ route('sales.show', $bill) }}" class="text-leaf hover:underline">{{ $number }}</a>
                                    @else
                                        <a href="{{ route('purchases.show', $bill) }}" class="text-leaf hover:underline">{{ $number }}</a>
                                    @endif
                                </td>
                                <td>{{ fmt_date($date) }}</td>
                                <td class="num">{{ money($bill->grand_total) }}</td>
                                <td class="num">{{ money($bill->paid_amount) }}</td>
                                <td class="num font-semibold text-accent">{{ money($due) }}</td>
                                <td class="num">
                                    <input type="number" step="0.01" min="0" max="{{ $due }}"
                                           name="allocate[{{ $bill->id }}]"
                                           value="{{ old('allocate.'.$bill->id, '') }}"
                                           class="input input-sm w-28 text-right" data-bulk-allocate disabled>
                                </td>
                                <td class="text-xs text-ink-soft" data-bulk-status>—</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('bill_ids') <p class="error-text">{{ $message }}</p> @enderror

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary" data-bulk-fill-full>{{ __('Fill selected dues') }}</button>
                <button class="btn-primary">{{ $kind === 'receipt' ? __('Allocate & receive') : __('Allocate & pay') }}</button>
            </div>
        </form>
    @endif
</x-app-layout>
