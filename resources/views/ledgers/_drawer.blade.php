<form method="POST"
      action="{{ $ledger->exists ? route($k['key'].'.update', $ledger) : route($k['key'].'.store') }}"
      data-ajax-form
      class="space-y-4">
    @csrf
    @if ($ledger->exists) @method('PUT') @endif
    @if (! empty($forSelect))
        <input type="hidden" name="select" value="1">
    @endif

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-field name="name" :label="__('Name (English)')" :value="$ledger->name" required
                 :hint="__('Marathi fills automatically as you type — you can still edit it.')" />
        <x-field name="name_mr" :label="__('Name (Marathi)')" :value="$ledger->name_mr"
                 :hint="__('Auto from English. Clear this box to resume auto-fill.')" />

        <x-select-field name="type" :label="__('Type')" :value="$ledger->type" required
            :options="collect($k['types'])->map(fn ($l) => __($l))->all()" :disabled="$ledger->is_system" />
        @if ($ledger->is_system)
            <input type="hidden" name="type" value="{{ $ledger->type }}">
        @endif

        @if ($k['family'] === 'party')
            <x-field name="phone" :label="__('Phone')" :value="$ledger->phone" />
            <x-field name="email" type="email" :label="__('Email')" :value="$ledger->email" />
            <x-field name="gstin" :label="__('GSTIN')" :value="$ledger->gstin" maxlength="15"
                     :hint="__('15-character GST number')" />
            <x-field name="pan" :label="__('PAN')" :value="$ledger->pan" maxlength="10" />

            <x-select-field name="area_id" :label="__('Area (farm location)')" :value="$ledger->area_id"
                :placeholder="__('Select area')"
                :options="($areas ?? collect())->mapWithKeys(fn ($a) => [$a->id => $a->display_name.($a->short_code ? ' ('.$a->short_code.')' : '')])->all()"
                :hint="__('Separate from village — used for recovery lists')" />
            <x-field name="village" :label="__('Village')" :value="$ledger->village" />
            <x-field name="city" :label="__('City')" :value="$ledger->city" />
            <x-select-field name="state_code" :label="__('State (place of supply)')" :value="$ledger->state_code ?: ($shellCompany->state_code ?? '27')"
                :options="$states" :placeholder="__('Select state')" />

            <x-field name="credit_limit" type="number" step="0.01" min="0" :label="__('Credit limit (₹)')" :value="$ledger->credit_limit"
                     :hint="__('0 = no limit. New parties use Owner / App control default.')" />
            <x-field name="credit_days" type="number" min="0" max="365" :label="__('Credit days')" :value="$ledger->credit_days"
                     :hint="__('Default from App control (usually 30)')" />

            <x-select-field name="salesperson_user_id" :label="__('Default salesperson')" :value="$ledger->salesperson_user_id"
                :placeholder="__('None')"
                :options="($salespeople ?? collect())->mapWithKeys(fn ($u) => [$u->id => $u->name])->all()"
                :hint="__('Used for commission when enabled in App control')" />
            <x-field name="commission_pct" type="number" step="0.01" min="0" max="100" :label="__('Commission %')" :value="$ledger->commission_pct"
                     :hint="__('Blank = shop default % from App control')" />

            <x-field name="address" :label="__('Address')" :value="$ledger->address" class="sm:col-span-2" />
        @endif

        <x-field name="opening_balance" type="number" step="0.01" min="0" :label="__('Opening balance')" :value="$ledger->opening_balance ?? 0" />
        <x-select-field name="opening_type" :label="__('Opening side (Dr / Cr)')" :value="$ledger->opening_type" required
            :options="['dr' => __('Debit (Dr)'), 'cr' => __('Credit (Cr)')]"
            :hint="$k['key'] === 'suppliers'
                ? __('Suppliers: Cr = you owe them; Dr = advance paid to them')
                : ($k['key'] === 'clients'
                    ? __('Customers: Dr = they owe you; Cr = advance received')
                    : __('Dr increases asset/expense; Cr increases liability/income'))" />
    </div>

    <x-check name="is_active" :label="__('Active')" :checked="$ledger->is_active" />

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Save') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
