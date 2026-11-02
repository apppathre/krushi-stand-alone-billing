<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('New Purchase')" :blurb="__('Enter the bill here. Open recent from the list button.')">
        <x-screen-help topic="purchase" />
        <a href="{{ route('purchase-orders.index') }}" class="btn-secondary btn-sm">{{ __('Purchase Order') }}</a>
        <button type="button" class="btn-secondary btn-sm"
                onclick="KrushiDrawer.open('{{ route('purchases.browse') }}', '{{ __('Recent purchases') }}', '52rem')">
            <x-icon name="list" class="h-4 w-4" /> <span class="page-head__btn-label">{{ __('Recent') }}</span>
        </button>
    </x-page-head>

    @if (! empty($fromOrder))
        <div class="mb-3 rounded-lg border border-leaf/30 bg-leaf/10 px-3 py-2 text-sm">
            {{ __('Converting PO') }} <strong>{{ $fromOrder->order_no }}</strong>
            — {{ __('Add batch / expiry if needed, then choose Credit or Cash payment below.') }}
        </div>
    @endif

    @can('menu', ['purchases', 'add'])
        <section class="mb-2" aria-label="{{ __('New Purchase') }}">
            <form method="POST" action="{{ route('purchases.store') }}"
                  class="txn-desk space-y-4"
                  x-data="invoiceForm({
                      mode: 'purchase',
                      products: @js($products),
                      places: @js($places->map(fn ($g) => ['id' => $g->id, 'name' => $g->display_name])->values()->all()),
                      defaultPlaceId: {{ (int) old('godown_id', $placeId) }},
                      racksByPlace: @js($racksByPlace ?? []),
                      gstRates: @js($gstRates ?? [0, 5, 12, 18, 28]),
                      parties: @js($suppliers->mapWithKeys(fn ($s) => [$s->id => $s->state_code])->all()),
                      creditDays: @js($suppliers->mapWithKeys(fn ($s) => [$s->id => $s->credit_days])->all()),
                      defaultCreditDays: {{ (int) ($defaultCreditDays ?? 30) }},
                      voucherDate: '{{ old('purchase_date', $voucherDate ?? now()->toDateString()) }}',
                      companyState: '{{ $companyState }}',
                      partyId: '{{ $prefillSupplier ?? old('supplier_ledger_id', '') }}',
                      supplyState: '{{ $prefillState ?? old('supply_state_code', $companyState) }}',
                      settlement: '{{ $settlement ?? 'credit' }}',
                      creditDaysBill: {{ old('credit_days') !== null && old('credit_days') !== '' ? (int) old('credit_days') : 'null' }},
                      cashDiscount: {{ (float) old('cash_discount', 0) }},
                      freightAmount: {{ (float) old('freight_amount', 0) }},
                      hammaliAmount: {{ (float) old('hammali_amount', 0) }},
                      paid: {{ (float) ($paid ?? old('paid_amount', 0)) }},
                      old: @js($prefillItems ?? old('items', [])),
                  })"
                  @krushi-party-saved.window="
                      if ($event.detail?.kind === 'suppliers' || $event.detail?.kind === 'supplier') {
                          const sel = document.getElementById('f-supplier_ledger_id');
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
                              partyId = String($event.detail.id);
                              if ($event.detail.state_code) {
                                  parties[String($event.detail.id)] = $event.detail.state_code;
                              }
                              setPartyCreditDays($event.detail.id, $event.detail.credit_days);
                              partyChanged();
                          }
                      }
                  "
                >
                @csrf
                @if (! empty($fromOrder))
                    <input type="hidden" name="purchase_order_id" value="{{ $fromOrder->id }}">
                @endif

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
                        <label class="label" for="f-purchase_date">{{ __('Purchase date') }} <span class="text-accent">*</span></label>
                        <input id="f-purchase_date" type="date" name="purchase_date" class="input" required
                               x-model="voucherDate"
                               value="{{ old('purchase_date', $voucherDate ?? now()->toDateString()) }}"
                               min="{{ optional($currentFinancialYear ?? null)?->start_date?->toDateString() }}"
                               max="{{ optional($currentFinancialYear ?? null)?->end_date?->toDateString() }}">
                        @error('purchase_date') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    @include('partials.voucher-number', [
                        'docType' => 'purchase',
                        'dateInput' => '[name=purchase_date]',
                        'numberFormats' => $numberFormats ?? [],
                    ])
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <label class="label mb-0" for="f-supplier_ledger_id">{{ __('Supplier') }} <span class="text-accent">*</span></label>
                            @can('menu', ['suppliers', 'add'])
                                <button type="button" class="btn-secondary btn-sm"
                                        title="{{ __('Add supplier') }}"
                                        onclick="KrushiDrawer.open('{{ route('suppliers.create', ['select' => 1]) }}', '{{ __('Add supplier') }}', '36rem')">
                                    <x-icon name="plus" class="h-3.5 w-3.5" /> {{ __('Add') }}
                                </button>
                            @endcan
                        </div>
                        <select id="f-supplier_ledger_id" name="supplier_ledger_id" x-model="partyId" @change="partyChanged()" class="input" required>
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected((string) ($prefillSupplier ?? old('supplier_ledger_id')) === (string) $s->id)>
                                    {{ $s->display_name }}{{ $s->phone ? ' · '.$s->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <x-field name="supplier_invoice_no" :label="__('Supplier invoice no')" />
                    <x-field name="supplier_invoice_date" type="date" :label="__('Supplier invoice date')" />
                    <div class="md:col-span-2">
                        <label class="label" for="f-supply_state_code">{{ __('Place of supply (state)') }}</label>
                        <select id="f-supply_state_code" name="supply_state_code" x-model="supplyState" class="input">
                            @foreach ($states as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="notes" :label="__('Notes')" class="md:col-span-2" />
                </div>

                @include('partials.txn-lines-purchase')

                @foreach ($errors->get('items.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach

                <div class="grid gap-4 lg:grid-cols-3">
                    <div>
                        <button type="button" class="btn-secondary w-full sm:w-auto" @click="add()"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add line') }}</button>
                    </div>
                    @include('partials.payment-fields', ['label' => __('Amount paid now')])
                    @include('partials.invoice-totals')
                </div>

                @include('partials.terms-editor', [
                    'value' => old('terms', $defaultTerms ?? ''),
                    'label' => __('Terms & conditions (this purchase)'),
                    'hint' => __('Saved on this bill. Default comes from Company settings.'),
                ])

                <div class="txn-sticky-actions flex flex-wrap gap-2">
                    <button class="btn-primary flex-1 sm:flex-none" data-busy-label="{{ __('Saving…') }}">{{ __('Save purchase') }}</button>
                </div>
            </form>
        </section>
    @endcan
</x-app-layout>
