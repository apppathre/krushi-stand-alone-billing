<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ $title }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="$title" :blurb="$blurb">
        <a href="{{ route('account-vouchers.register') }}" class="btn-secondary btn-sm">{{ __('Voucher register') }}</a>
        @foreach ([
            'payment' => __('Payment'),
            'receipt' => __('Receipt'),
            'journal' => __('Journal'),
            'contra' => __('Contra'),
        ] as $k => $label)
            @if ($k !== $kind)
                <a href="{{ route('account-vouchers.'.$k) }}" class="btn-secondary btn-sm">{{ $label }}</a>
            @endif
        @endforeach
    </x-page-head>

    <div class="voucher-desk grid gap-4 lg:grid-cols-5">
        <section class="card card-body lg:col-span-3" aria-label="{{ $title }}">
            <form method="POST" action="{{ route('account-vouchers.'.$kind.'.store') }}" class="space-y-3" data-busy-label="{{ __('Saving…') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <x-field name="voucher_date" type="date" :label="__('Date')" :value="old('voucher_date', $voucherDate)"
                             :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                             :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
                    @if (count($formats) > 1)
                        <div>
                            <label class="label" for="f-format_id">{{ __('Number series') }}</label>
                            <select id="f-format_id" name="format_id" class="input">
                                @foreach ($formats as $f)
                                    <option value="{{ $f['id'] }}" @selected((int) old('format_id', $defaultFormatId) === (int) $f['id'])>
                                        {{ $f['name'] }} ({{ $f['preview'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="format_id" value="{{ $defaultFormatId }}">
                        <div>
                            <label class="label">{{ __('Next number') }}</label>
                            <p class="input bg-sage/30 font-semibold text-leaf-dark">{{ $previewNo }}</p>
                        </div>
                    @endif
                    @if ($places->count() > 1)
                        <div>
                            <label class="label" for="f-godown_id">{{ __('Place') }}</label>
                            <select id="f-godown_id" name="godown_id" class="input">
                                <option value="">{{ __('Whole shop') }}</option>
                                @foreach ($places as $g)
                                    <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === (int) $g->id)>{{ $g->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($placeId)
                        <input type="hidden" name="godown_id" value="{{ $placeId }}">
                    @endif
                </div>

                <x-field name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('amount')" required />

                @if ($kind === 'payment' || $kind === 'receipt')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="f-cash_ledger_id">{{ $kind === 'payment' ? __('Paid from') : __('Received in') }}</label>
                            <select id="f-cash_ledger_id" name="cash_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($cashLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('cash_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('cash_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="f-account_ledger_id">
                                {{ $kind === 'payment' ? __('Charge to (rent / salary / expense / party)') : __('Credit to (income / party)') }}
                            </label>
                            <select id="f-account_ledger_id" name="account_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($accountLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('account_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('account_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @if ($kind === 'payment' && $staff->isNotEmpty())
                        <div>
                            <label class="label" for="f-staff_user_id">{{ __('Staff (for salary)') }}</label>
                            <select id="f-staff_user_id" name="staff_user_id" class="input">
                                <option value="">{{ __('Optional') }}</option>
                                @foreach ($staff as $u)
                                    <option value="{{ $u->id }}" @selected((string) old('staff_user_id') === (string) $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @elseif ($kind === 'journal')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="f-debit_ledger_id">{{ __('Debit (Dr)') }}</label>
                            <select id="f-debit_ledger_id" name="debit_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($accountLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('debit_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('debit_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="f-credit_ledger_id">{{ __('Credit (Cr)') }}</label>
                            <select id="f-credit_ledger_id" name="credit_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($accountLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('credit_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('credit_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-ink-soft">{{ __('Example: Dr Commission Payable / Cr Bank — or Dr Customer / Cr Interest Income.') }}</p>
                @elseif ($kind === 'contra')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="f-from_ledger_id">{{ __('From (out)') }}</label>
                            <select id="f-from_ledger_id" name="from_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($cashLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('from_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('from_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="f-to_ledger_id">{{ __('To (in)') }}</label>
                            <select id="f-to_ledger_id" name="to_ledger_id" class="input" required>
                                <option value="">{{ __('Select…') }}</option>
                                @foreach ($cashLedgers as $id => $label)
                                    <option value="{{ $id }}" @selected((string) old('to_ledger_id') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('to_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-ink-soft">{{ __('Example: From Cash → To Bank (deposit), or Bank → GPay.') }}</p>
                @endif

                <x-field name="narration" :label="__('Narration')" :value="old('narration')" :placeholder="__('Short note for the books')" />

                <button class="btn-primary w-full sm:w-auto">{{ __('Save voucher') }}</button>
            </form>
        </section>

        <aside class="card card-body lg:col-span-2" aria-label="{{ __('Recent') }}">
            <h2 class="card-title mb-3">{{ __('Recent') }}</h2>
            <ul class="divide-y divide-sage text-sm">
                @forelse ($recent as $v)
                    <li class="py-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-leaf-dark">{{ $v->voucher_no }}</p>
                                <p class="truncate text-xs text-ink-soft">{{ fmt_date($v->voucher_date) }}
                                    @if ($v->staff) · {{ $v->staff->name }} @endif
                                    @if ($v->narration) · {{ $v->narration }} @endif
                                </p>
                                <p class="mt-0.5 text-xs text-ink-soft">
                                    @foreach ($v->lines as $line)
                                        {{ $line->ledger?->display_name }}{{ ! $loop->last ? ' / ' : '' }}
                                    @endforeach
                                </p>
                            </div>
                            <span class="shrink-0 font-semibold">&#8377; {{ money($v->amount) }}</span>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-ink-soft">{{ __('No vouchers yet.') }}</li>
                @endforelse
            </ul>
        </aside>
    </div>
</x-app-layout>
