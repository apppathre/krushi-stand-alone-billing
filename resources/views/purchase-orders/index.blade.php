@php
    $isEdit = isset($editing) && $editing;
    $formItems = old('items', $editItems ?? []);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ $isEdit ? __('Amend PO') : __('Purchase Order') }}</h1>
    </x-slot>

    <x-page-head class="mb-3"
                 :title="$isEdit ? __('Amend').' '.$editing->order_no : __('Purchase Order')"
                 :blurb="__('Order goods from supplier — no stock or money until you convert to Purchase.')">
        <x-screen-help topic="purchase_order" />
        @if (! $isEdit)
            <button type="button" class="btn-secondary btn-sm"
                    onclick="KrushiDrawer.open('{{ route('purchase-orders.browse') }}', '{{ __('Recent POs') }}', '48rem')">
                <x-icon name="list" class="h-4 w-4" /> <span class="page-head__btn-label">{{ __('Recent') }}</span>
            </button>
            <a href="{{ route('purchases.index') }}" class="btn-secondary btn-sm">{{ __('Purchase bill') }}</a>
        @else
            <a href="{{ route('purchase-orders.show', $editing) }}" class="btn-secondary btn-sm">{{ __('Cancel edit') }}</a>
        @endif
    </x-page-head>

    @if ($isEdit || auth()->user()->canMenu('purchase_orders', 'add'))
        <section class="mb-2" aria-label="{{ __('Purchase Order') }}">
            <form method="POST"
                  action="{{ $isEdit ? route('purchase-orders.update', $editing) : route('purchase-orders.store') }}"
                  class="txn-desk space-y-4"
                  x-data="invoiceForm({
                      mode: 'purchase',
                      products: @js($products),
                      places: @js($places->map(fn ($g) => ['id' => $g->id, 'name' => $g->display_name])->values()->all()),
                      defaultPlaceId: {{ (int) old('godown_id', $placeId) }},
                      racksByPlace: {},
                      gstRates: @js($gstRates ?? [0, 5, 12, 18, 28]),
                      parties: @js($suppliers->mapWithKeys(fn ($s) => [$s->id => $s->state_code])->all()),
                      companyState: '{{ $companyState }}',
                      partyId: '{{ old('supplier_ledger_id', $editing->supplier_ledger_id ?? '') }}',
                      supplyState: '{{ old('supply_state_code', $editing->supply_state_code ?? $companyState) }}',
                      paid: 0,
                      old: @js($formItems),
                  })"
                  data-busy-label="{{ __('Saving…') }}">
                @csrf
                @if ($isEdit) @method('PUT') @endif

                <div class="card card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="label" for="f-godown_id">{{ __('Place') }} <span class="text-accent">*</span></label>
                        <select id="f-godown_id" name="godown_id" class="input" required
                                x-model="defaultPlaceId" @change="setDefaultPlace($event.target.value)">
                            @foreach ($places as $g)
                                <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === $g->id)>{{ $g->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="order_date" type="date" :label="__('Order date')"
                             :value="old('order_date', $voucherDate ?? now()->toDateString())"
                             :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()"
                             :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()"
                             required />
                    <x-field name="expected_date" type="date" :label="__('Expected delivery')"
                             :value="old('expected_date', $editing?->expected_date?->toDateString())" />
                    @if (! $isEdit)
                        @include('partials.voucher-number', [
                            'docType' => 'purchase_order',
                            'dateInput' => '[name=order_date]',
                            'numberFormats' => $numberFormats ?? [],
                        ])
                    @else
                        <div>
                            <label class="label">{{ __('Number') }}</label>
                            <input class="input bg-sage/30" value="{{ $editing->order_no }}" readonly>
                        </div>
                    @endif
                    <div class="md:col-span-2">
                        <label class="label" for="f-supplier_ledger_id">{{ __('Supplier') }} <span class="text-accent">*</span></label>
                        <select id="f-supplier_ledger_id" name="supplier_ledger_id" x-model="partyId" @change="partyChanged()" class="input" required>
                            <option value="">{{ __('Select supplier') }}</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" @selected((string) old('supplier_ledger_id', $editing->supplier_ledger_id ?? '') === (string) $s->id)>
                                    {{ $s->display_name }}{{ $s->phone ? ' · '.$s->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="label" for="f-supply_state_code">{{ __('Place of supply (state)') }}</label>
                        <select id="f-supply_state_code" name="supply_state_code" x-model="supplyState" class="input">
                            @foreach ($states as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-field name="notes" :label="__('Notes')" :value="old('notes', $editing->notes ?? '')" class="md:col-span-2" />
                </div>

                @include('partials.txn-lines-po')
                @foreach ($errors->get('items') as $m) <p class="error-text">{{ $m }}</p> @endforeach
                @foreach ($errors->get('items.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach

                <div class="grid gap-4 lg:grid-cols-3">
                    <div>
                        <button type="button" class="btn-secondary w-full sm:w-auto" @click="add()"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add line') }}</button>
                    </div>
                    <div class="lg:col-span-2">@include('partials.invoice-totals')</div>
                </div>

                @include('partials.terms-editor', [
                    'value' => old('terms', $defaultTerms ?? ''),
                    'label' => __('Terms & conditions'),
                ])

                <div class="txn-sticky-actions flex flex-wrap gap-2">
                    <button class="btn-primary flex-1 sm:flex-none">{{ $isEdit ? __('Update PO') : __('Save purchase order') }}</button>
                </div>
            </form>
        </section>
    @endif
</x-app-layout>
