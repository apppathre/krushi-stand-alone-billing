<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('New Purchase') }}</h1>
        <p class="text-xs text-ink-soft">{{ __('Stock posts to the place you select on this bill.') }}</p>
    </x-slot>

    <form method="POST" action="{{ route('purchases.store') }}"
          x-data="invoiceForm({
              mode: 'purchase',
              products: @js($products),
              parties: @js($suppliers->mapWithKeys(fn ($s) => [$s->id => $s->state_code])->all()),
              companyState: '{{ $companyState }}',
              partyId: '{{ old('supplier_ledger_id') }}',
              supplyState: '{{ old('supply_state_code', $companyState) }}',
              paid: {{ (float) old('paid_amount', 0) }},
              old: @js(old('items', [])),
          })" class="space-y-4">
        @csrf

        <div class="card card-body grid gap-4 md:grid-cols-4">
            <div>
                <label class="label" for="f-godown_id">{{ __('Place / Godown') }} <span class="text-accent">*</span></label>
                <select id="f-godown_id" name="godown_id" class="input" required>
                    @foreach ($places as $g)
                        <option value="{{ $g->id }}" @selected((int) old('godown_id', $placeId) === $g->id)>{{ $g->display_name }}</option>
                    @endforeach
                </select>
                @error('godown_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <x-field name="purchase_date" type="date" :label="__('Purchase date')" :value="old('purchase_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
            <div>
                <label class="label" for="f-supplier_ledger_id">{{ __('Supplier') }} <span class="text-accent">*</span></label>
                <select id="f-supplier_ledger_id" name="supplier_ledger_id" x-model="partyId" @change="partyChanged()" class="input" required>
                    <option value="">{{ __('Select supplier') }}</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->display_name }}</option>
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

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="min-w-[14rem]">{{ __('Product') }}</th><th>{{ __('Batch no') }}</th><th>{{ __('Mfg') }}</th><th>{{ __('Expiry') }}</th>
                        <th>{{ __('Qty') }}</th><th>{{ __('Rate') }}</th><th>{{ __('MRP') }}</th><th>{{ __('Disc %') }}</th>
                        <th class="num">{{ __('GST %') }}</th><th class="num">{{ __('Amount') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in lines" :key="line.key">
                        <tr data-line>
                            <td>
                                <select :name="`items[${i}][product_id]`" x-model="line.product_id" @change="pick(line)" class="input input-sm" required>
                                    <option value="">{{ __('Select product') }}</option>
                                    <template x-for="p in products" :key="p.id"><option :value="p.id" :selected="String(p.id) === String(line.product_id)" x-text="p.name + (p.code ? ' (' + p.code + ')' : '')"></option></template>
                                </select>
                            </td>
                            <td><input :name="`items[${i}][batch_no]`" x-model="line.batch_no" class="input input-sm w-28" :required="product(line)?.batch_required" :disabled="product(line) && !product(line).batch_required"></td>
                            <td><input type="date" :name="`items[${i}][mfg_date]`" x-model="line.mfg_date" class="input input-sm w-32" :disabled="product(line) && !product(line).batch_required"></td>
                            <td><input type="date" :name="`items[${i}][expiry_date]`" x-model="line.expiry_date" class="input input-sm w-32" :disabled="product(line) && !product(line).batch_required"></td>
                            <td><input type="number" step="0.001" min="0.001" :name="`items[${i}][qty]`" x-model="line.qty" class="input input-sm w-20" required></td>
                            <td><input type="number" step="0.01" min="0" :name="`items[${i}][rate]`" x-model="line.rate" class="input input-sm w-24" required></td>
                            <td><input type="number" step="0.01" min="0" :name="`items[${i}][mrp]`" x-model="line.mrp" class="input input-sm w-24"></td>
                            <td><input type="number" step="0.01" min="0" max="100" :name="`items[${i}][discount_pct]`" x-model="line.discount_pct" class="input input-sm w-16"></td>
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
            @include('partials.payment-fields', ['label' => __('Amount paid now')])
            @include('partials.invoice-totals')
        </div>

        <div class="flex gap-2">
            <button class="btn-primary">{{ __('Save purchase') }}</button>
            <a href="{{ route('purchases.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
