@php
    $isEdit = ! empty($editing);
    $formItems = old('items', $editItems ?? []);
    $partyPrefill = old('client_ledger_id', $editing?->client_ledger_id ?? '');
    $statePrefill = old('supply_state_code', $editing?->supply_state_code ?? $companyState);
    $settlePrefill = old('settlement_type', $editing?->settlement_type ?: (((float) ($editing?->paid_amount ?? 0) > 0) ? 'cash' : 'credit'));
    $paidPrefill = (float) old('paid_amount', $editing?->paid_amount ?? 0);
    $termsPrefill = old('terms', $defaultTerms ?? ($editing?->terms ?? ''));
    $creditDaysBill = old('credit_days', $editing?->credit_days);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ $isEdit ? __('Amend invoice') : __('Sales') }}</h1>
    </x-slot>

    <x-page-head class="mb-3"
                 :title="$isEdit ? __('Amend').' '.$editing->invoice_no : __('New Invoice')"
                 :blurb="$isEdit ? __('Update lines, customer, or payment. Number stays the same. Existing receipts are replaced from this form.') : __('Enter the invoice here. Open recent from the list button.')">
        <x-screen-help topic="sale" />
        @if (! $isEdit)
            <button type="button" class="btn-secondary btn-sm"
                    onclick="KrushiDrawer.open('{{ route('sales.browse', ['preset' => 'month']) }}', '{{ __('Recent invoices') }}', '80rem')">
                <x-icon name="list" class="h-4 w-4" /> <span class="page-head__btn-label">{{ __('Recent') }}</span>
            </button>
        @else
            <a href="{{ route('sales.show', $editing) }}" class="btn-secondary btn-sm">{{ __('Cancel edit') }}</a>
        @endif
    </x-page-head>

    @if ($isEdit || auth()->user()->canMenu('sales', 'add'))
        <section class="mb-2" aria-label="{{ $isEdit ? __('Amend invoice') : __('New Invoice') }}">
            <form method="POST"
                  action="{{ $isEdit ? route('sales.update', $editing) : route('sales.store') }}"
                  class="txn-desk space-y-4"
                  data-busy-label="{{ $isEdit ? __('Updating invoice…') : __('Saving invoice…') }}"
                  x-data="invoiceForm({
                      mode: 'sale',
                      products: @js($products),
                      places: @js($places->map(fn ($g) => ['id' => $g->id, 'name' => $g->display_name])->values()->all()),
                      defaultPlaceId: {{ (int) old('godown_id', $placeId) }},
                      gstRates: @js($gstRates ?? [0, 5, 12, 18, 28]),
                      parties: @js($clients->mapWithKeys(fn ($c) => [$c->id => $c->state_code])->all()),
                      creditDays: @js($clients->mapWithKeys(fn ($c) => [$c->id => $c->credit_days])->all()),
                      defaultCreditDays: {{ (int) ($defaultCreditDays ?? 30) }},
                      voucherDate: '{{ old('invoice_date', $voucherDate ?? now()->toDateString()) }}',
                      companyState: '{{ $companyState }}',
                      partyId: '{{ $partyPrefill }}',
                      supplyState: '{{ $statePrefill }}',
                      settlement: '{{ $settlePrefill }}',
                      creditDaysBill: {{ $creditDaysBill !== null && $creditDaysBill !== '' ? (int) $creditDaysBill : 'null' }},
                      cashDiscount: {{ (float) old('cash_discount', $editing?->cash_discount ?? 0) }},
                      freightAmount: {{ (float) old('freight_amount', $editing?->freight_amount ?? 0) }},
                      hammaliAmount: {{ (float) old('hammali_amount', $editing?->hammali_amount ?? 0) }},
                      paid: {{ $paidPrefill }},
                      old: @js($formItems),
                  })"
                  @krushi-party-saved.window="
                      if ($event.detail?.kind === 'clients' || $event.detail?.kind === 'client') {
                          const sel = document.getElementById('f-client_ledger_id');
                          if (sel && $event.detail.id) {
                              const label = $event.detail.name + ($event.detail.phone ? ' · ' + $event.detail.phone : '');
                              let opt = [...sel.options].find(o => String(o.value) === String($event.detail.id));
                              if (!opt) {
                                  opt = new Option(label, $event.detail.id, true, true);
                                  sel.add(opt);
                              } else {
                                  opt.text = label;
                                  opt.selected = true;
                              }
                              if ($event.detail.state_code) {
                                  parties[String($event.detail.id)] = $event.detail.state_code;
                              }
                              setPartyCreditDays($event.detail.id, $event.detail.credit_days);
                              refreshPartySelect($event.detail.id);
                          }
                      }
                  "
                >
                @csrf
                @if ($isEdit) @method('PUT') @endif

                <div class="card card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label" for="f-godown_id">{{ __('Place') }} <span class="text-accent">*</span></label>
                        <select id="f-godown_id" name="godown_id" class="input" required
                                x-model="defaultPlaceId"
                                @change="setDefaultPlace($event.target.value)">
                            @foreach ($places as $g)
                                <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === $g->id)>{{ $g->display_name }}</option>
                            @endforeach
                        </select>
                        @error('godown_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="f-invoice_date">{{ __('Invoice date') }} <span class="text-accent">*</span></label>
                        <input id="f-invoice_date" type="date" name="invoice_date" class="input" required
                               x-model="voucherDate"
                               value="{{ old('invoice_date', $voucherDate ?? now()->toDateString()) }}"
                               min="{{ optional($currentFinancialYear ?? null)?->start_date?->toDateString() }}"
                               max="{{ optional($currentFinancialYear ?? null)?->end_date?->toDateString() }}">
                        @error('invoice_date') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    @if ($isEdit)
                        <div>
                            <label class="label" for="f-invoice_no">{{ __('Invoice no') }}</label>
                            <input id="f-invoice_no" type="text" class="input bg-cream/40" value="{{ $editing->invoice_no }}" readonly tabindex="-1">
                            <p class="mt-1 text-xs text-ink-soft">{{ __('Number stays the same when you amend.') }}</p>
                        </div>
                    @else
                        @include('partials.voucher-number', [
                            'docType' => 'sale',
                            'dateInput' => '[name=invoice_date]',
                            'numberFormats' => $numberFormats ?? [],
                        ])
                    @endif
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="label mb-0" for="f-client_ledger_id">{{ __('Customer') }} <span class="text-accent">*</span></label>
                            @can('menu', ['clients', 'add'])
                                <button type="button" class="btn-secondary btn-sm"
                                        title="{{ __('Add customer') }}"
                                        onclick="KrushiDrawer.open('{{ route('clients.create', ['select' => 1]) }}', '{{ __('Add customer') }}', '36rem')">
                                    <x-icon name="plus" class="h-3.5 w-3.5" /> {{ __('Add') }}
                                </button>
                            @endcan
                        </div>
                        <select id="f-client_ledger_id" name="client_ledger_id" class="input js-party-select js-searchable" required
                                data-placeholder="{{ __('Select customer') }}">
                            <option value="">{{ __('Select customer') }}</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected((string) $partyPrefill === (string) $c->id)>
                                    {{ $c->display_name }}{{ $c->phone ? ' · '.$c->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-soft">{{ __('Customer ledger is required. Walk-in is not allowed.') }}</p>
                        @error('client_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <x-field name="customer_phone" :label="__('Phone (optional note)')" :value="old('customer_phone', $editing?->customer_phone ?? '')" />
                    <div class="md:col-span-2">
                        <label class="label" for="f-supply_state_code">{{ __('Place of supply (state)') }}</label>
                        <select id="f-supply_state_code" name="supply_state_code" x-model="supplyState" class="input">
                            @foreach ($states as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="notes" :label="__('Notes')" class="md:col-span-2" :value="old('notes', $editing?->notes ?? '')" />
                </div>

                @include('partials.txn-lines-sale')

                @foreach ($errors->get('items.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach
                @error('items') <p class="error-text">{{ $message }}</p> @enderror
                @error('sale') <p class="error-text">{{ $message }}</p> @enderror

                <div class="grid gap-4 lg:grid-cols-3">
                    <div>
                        <button type="button" class="btn-secondary w-full sm:w-auto" @click="add()"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add line') }}</button>
                    </div>
                    @include('partials.payment-fields')
                    @include('partials.invoice-totals')
                </div>

                @include('partials.terms-editor', [
                    'value' => $termsPrefill,
                    'label' => __('Terms & conditions (this invoice)'),
                    'hint' => __('Edit with the editor, then Save or Save & Print. Default comes from Company settings.'),
                ])

                <div class="txn-sticky-actions flex flex-wrap gap-2">
                    <button type="submit" name="after_save" value="show" class="btn-secondary flex-1 sm:flex-none" data-busy-label="{{ $isEdit ? __('Updating invoice…') : __('Saving invoice…') }}">{{ $isEdit ? __('Update') : __('Save') }}</button>
                    <button type="submit" name="after_save" value="print" class="btn-primary flex-1 sm:flex-none" data-busy-label="{{ $isEdit ? __('Updating invoice…') : __('Saving invoice…') }}">{{ $isEdit ? __('Update & Print') : __('Save & Print') }}</button>
                </div>
            </form>
        </section>
    @endif
</x-app-layout>
