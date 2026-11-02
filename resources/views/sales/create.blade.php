<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('New Invoice') }}</h1>
        <p class="text-xs text-ink-soft">{{ __('Stock posts to the place you select on this bill.') }}</p>
    </x-slot>

    <form method="POST" action="{{ route('sales.store') }}"
          x-data="invoiceForm({
              mode: 'sale',
              products: @js($products),
              parties: @js($clients->mapWithKeys(fn ($c) => [$c->id => $c->state_code])->all()),
              companyState: '{{ $companyState }}',
              partyId: '{{ old('client_ledger_id', $walkInId) }}',
              supplyState: '{{ old('supply_state_code', $companyState) }}',
              paid: {{ (float) old('paid_amount', 0) }},
              old: @js(old('items', [])),
          })" class="txn-desk space-y-4" data-busy-label="{{ __('Saving invoice…') }}">
        @csrf

        <div class="card card-body grid gap-4 md:grid-cols-4">
            <div>
                <label class="label" for="f-godown_id">{{ __('Place / Godown') }} <span class="text-accent">*</span></label>
                <select id="f-godown_id" name="godown_id" class="input" required
                        onchange="window.location='{{ route('sales.create') }}?godown_id='+this.value">
                    @foreach ($places as $g)
                        <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === $g->id)>{{ $g->display_name }}</option>
                    @endforeach
                </select>
                @error('godown_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <x-field name="invoice_date" type="date" :label="__('Invoice date')" :value="old('invoice_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
            <div>
                <label class="label" for="f-client_ledger_id">{{ __('Client') }}</label>
                <select id="f-client_ledger_id" name="client_ledger_id" class="input js-party-select js-searchable"
                        data-placeholder="{{ __('Select customer') }}">
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->display_name }}{{ $c->phone ? ' · '.$c->phone : '' }}</option>
                    @endforeach
                </select>
                @error('client_ledger_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <x-field name="customer_name" :label="__('Customer name (walk-in)')" />
            <x-field name="customer_phone" :label="__('Customer phone')" />
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

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="min-w-[14rem]">{{ __('Product') }}</th><th class="min-w-[11rem]">{{ __('Batch') }}</th>
                        <th>{{ __('Mfg') }}</th><th>{{ __('Expiry') }}</th>
                        <th>{{ __('Qty') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Disc %') }}</th>
                        <th class="num">{{ __('GST %') }}</th><th class="num">{{ __('Amount') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="line.key">
                        <tr data-line>
                            <td>
                                <select :name="`items[${i}][product_id]`"
                                        class="input input-sm js-txn-product js-sale-product"
                                        required
                                        x-init="$nextTick(() => mountProductSelect($el, line))"></select>
                                <p class="mt-0.5 text-xs text-ink-soft" x-show="product(line)">
                                    HSN <span x-text="product(line)?.hsn || '-'"></span>
                                    <template x-if="available(line) !== null"><span> · {{ __('Stock here') }}: <strong x-text="available(line)"></strong></span></template>
                                    <template x-if="product(line)"><span> · {{ __('All places') }}: <strong x-text="product(line)?.stock ?? 0"></strong></span></template>
                                </p>
                            </td>
                            <td>
                                <template x-if="product(line) && product(line).batch_required && batches(line).length">
                                    <select :name="`items[${i}][batch_id]`" x-model="line.batch_id" @change="pickBatch(line)" class="input input-sm" :required="product(line).batch_required">
                                        <template x-for="b in batches(line)" :key="b.id">
                                            <option :value="b.id" :selected="String(b.id) === String(line.batch_id)" x-text="b.batch_no + (b.mfg ? ' · mfg ' + b.mfg : '') + (b.expiry ? ' · exp ' + b.expiry : '') + ' · ' + b.qty"></option>
                                        </template>
                                    </select>
                                </template>
                                <span x-show="product(line) && !batches(line).length" class="text-xs font-semibold text-red-600">{{ __('Out of stock') }}</span>
                            </td>
                            <td class="whitespace-nowrap text-xs text-ink-soft" x-text="batch(line)?.mfg || '—'"></td>
                            <td class="whitespace-nowrap text-xs text-ink-soft" x-text="batch(line)?.expiry || '—'"></td>
                            <td>
                                <input type="number" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm w-24" required
                                       :class="overStock(line) ? 'border-red-500' : ''">
                                <p class="text-xs text-red-600" x-show="overStock(line)">{{ __('Exceeds stock') }}</p>
                            </td>
                            <td><input type="number" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm w-28" required></td>
                            <td><input type="number" step="0.01" min="0" max="100" :name="`items[${i}][discount_pct]`" x-model="line.discount_pct" class="input input-sm w-20"></td>
                            <td class="num" x-text="line.gst"></td>
                            <td class="num font-semibold" x-text="money(calc(line).total)"></td>
                            <td><button type="button" class="btn-danger btn-sm" @click="remove(i)" title="{{ __('Remove') }}">&times;</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        @foreach ($errors->get('items.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach

        <div class="grid gap-4 lg:grid-cols-3">
            <div>
                <button type="button" class="btn-secondary" @click="add()"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add line') }}</button>
            </div>
            @include('partials.payment-fields')
            @include('partials.invoice-totals')
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">{{ __('Save invoice') }}</button>
            <a href="{{ route('sales.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
