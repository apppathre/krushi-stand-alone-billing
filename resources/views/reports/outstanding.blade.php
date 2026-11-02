<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title flex items-center gap-2"><x-icon name="outstanding" class="h-7 w-7 text-leaf" /> {{ __('Outstanding Report') }}</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <span class="btn-primary btn-sm">{{ __('Party summary') }}</span>
        <a href="{{ route('reports.outstanding', ['view' => 'ageing', 'side' => 'receivable']) }}" class="btn-secondary btn-sm">{{ __('Bill-wise ageing') }}</a>
    </div>

    <x-auto-filter>
        <x-select-field name="side" :label="__('Type')" :value="$side" :placeholder="__('All')"
            :options="['receivable' => __('Receivable (Customers)'), 'payable' => __('Payable (Suppliers)')]" class="w-full sm:w-56" />
        <input type="hidden" name="view" value="party">
        <x-slot:actions>
            <x-report-export />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results" class="grid gap-6 lg:grid-cols-2">
        @if ($side !== 'payable')
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-sage bg-sage/40 px-5 py-3">
                    <h2 class="card-title">{{ __('Receivable from Clients') }}</h2>
                    <span class="font-semibold text-accent">&#8377; {{ money($receivable) }}</span>
                </div>
                <div class="table-wrap border-0">
                    <table class="table">
                        <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Phone') }}</th><th class="num">{{ __('Due') }}</th><th class="no-print">{{ __('Follow-up') }}</th></tr></thead>
                        <tbody>
                            @forelse ($receivable_rows as $r)
                                <tr x-data="partyOutreach(@js(route('party.outreach', $r->id)))">
                                    <td><a class="font-semibold text-leaf hover:underline" href="{{ route('reports.party-ledger', ['ledger_id' => $r->id]) }}">{{ app()->getLocale() === 'mr' && $r->name_mr ? $r->name_mr : $r->name }}</a></td>
                                    <td>{{ $r->phone }}</td>
                                    <td class="num font-semibold">{{ money($r->bal) }}</td>
                                    <td class="no-print">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('call')" title="{{ __('Call') }}">{{ __('Call') }}</button>
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('whatsapp')" title="{{ __('WhatsApp') }}">{{ __('WhatsApp') }}</button>
                                            <label class="ml-1 inline-flex items-center gap-1 text-[0.7rem] text-ink-soft">
                                                <input type="checkbox" class="rounded border-sage-dark" x-model="withLink"> {{ __('Link') }}
                                            </label>
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy || !withLink" @click="open('copy')" title="{{ __('Copy statement link') }}">{{ __('Copy link') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-ink-soft">{{ __('No receivables.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($side !== 'receivable')
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-sage bg-sage/40 px-5 py-3">
                    <h2 class="card-title">{{ __('Payable to Suppliers') }}</h2>
                    <span class="font-semibold text-accent">&#8377; {{ money($payable) }}</span>
                </div>
                <div class="table-wrap border-0">
                    <table class="table">
                        <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Phone') }}</th><th class="num">{{ __('Due') }}</th><th class="no-print">{{ __('Follow-up') }}</th></tr></thead>
                        <tbody>
                            @forelse ($payable_rows as $r)
                                <tr x-data="partyOutreach(@js(route('party.outreach', $r->id)), { with_link: false })">
                                    <td><a class="font-semibold text-leaf hover:underline" href="{{ route('reports.party-ledger', ['ledger_id' => $r->id]) }}">{{ app()->getLocale() === 'mr' && $r->name_mr ? $r->name_mr : $r->name }}</a></td>
                                    <td>{{ $r->phone }}</td>
                                    <td class="num font-semibold">{{ money(abs($r->bal)) }}</td>
                                    <td class="no-print">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('call')">{{ __('Call') }}</button>
                                            <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('whatsapp')">{{ __('WhatsApp') }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-ink-soft">{{ __('No payables.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
