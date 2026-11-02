<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Account voucher register') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Account voucher register')" :blurb="__('All payment, receipt, journal and contra vouchers.')">
        <a href="{{ route('account-vouchers.payment') }}" class="btn-secondary btn-sm">{{ __('Payment') }}</a>
        <a href="{{ route('account-vouchers.receipt') }}" class="btn-secondary btn-sm">{{ __('Receipt') }}</a>
        <a href="{{ route('account-vouchers.journal') }}" class="btn-secondary btn-sm">{{ __('Journal') }}</a>
        <a href="{{ route('account-vouchers.contra') }}" class="btn-secondary btn-sm">{{ __('Contra') }}</a>
    </x-page-head>

    <x-auto-filter>
        <x-select-field name="type" :label="__('Type')" :value="$type" :placeholder="__('All')" :options="$typeOptions" class="w-full sm:w-52" />
        <x-field name="from" type="date" :label="__('From')" :value="request('from')" class="w-full sm:w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="request('to')" class="w-full sm:w-40" />
    </x-auto-filter>

    <div id="list-results" class="card overflow-hidden">
        <div class="table-wrap border-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Voucher') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Accounts') }}</th>
                        <th class="num">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $v)
                        <tr>
                            <td class="whitespace-nowrap">{{ fmt_date($v->voucher_date) }}</td>
                            <td class="font-semibold">{{ $v->voucher_no }}</td>
                            <td>{{ $typeOptions[$v->voucher_type] ?? $v->voucher_type }}</td>
                            <td class="text-sm">
                                @foreach ($v->lines as $line)
                                    <span class="block">{{ $line->debit > 0 ? 'Dr' : 'Cr' }} {{ $line->ledger?->display_name }}</span>
                                @endforeach
                                @if ($v->narration)<span class="block text-xs text-ink-soft">{{ $v->narration }}</span>@endif
                            </td>
                            <td class="num font-semibold">{{ money($v->amount) }}</td>
                            <td>
                                @if ($v->isCancelled())
                                    <span class="badge-gray">{{ __('Cancelled') }}</span>
                                @else
                                    <span class="badge-green">{{ __('Active') }}</span>
                                @endif
                            </td>
                            <td class="no-print">
                                @unless ($v->isCancelled())
                                    <button type="button" class="btn-danger btn-sm"
                                            data-confirm-submit
                                            data-confirm-message="{{ __('Cancel this voucher? Ledger entries will be removed.') }}"
                                            data-confirm-action="{{ route('account-vouchers.cancel', $v) }}"
                                            data-confirm-method="DELETE">
                                        {{ __('Cancel') }}
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-ink-soft">{{ __('No vouchers yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-sage px-4 py-3">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
