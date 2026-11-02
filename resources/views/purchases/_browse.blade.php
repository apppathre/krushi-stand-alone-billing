{{-- Loaded in side drawer: filters + AJAX list --}}
<div class="space-y-3" x-data>
    <x-auto-filter target="#purchase-drawer-results" :action="route('purchases.browse')">
        <div class="flex w-full flex-wrap items-center gap-1.5">
            <button type="button" class="btn-secondary btn-sm" @click="setRange('today')">{{ __('Today') }}</button>
            <button type="button" class="btn-secondary btn-sm" @click="setRange('yesterday')">{{ __('Yesterday') }}</button>
            <button type="button" class="btn-secondary btn-sm" @click="setRange('month')">{{ __('This month') }}</button>
        </div>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Purchase no, supplier…')" class="w-full sm:w-48" />
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-full sm:w-36" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-full sm:w-36" />
        <x-select-field name="supplier_id" :label="__('Supplier')" :value="request('supplier_id')" :placeholder="__('All')"
            :options="$filterSuppliers->mapWithKeys(fn ($s) => [$s->id => $s->display_name])->all()" class="w-full sm:w-44" />
        <x-select-field name="status" :label="__('Status')" :value="request('status')" :placeholder="__('All')"
            :options="['active' => __('Active'), 'cancelled' => __('Cancelled')]" class="w-full sm:w-36" />
    </x-auto-filter>

    <div id="purchase-drawer-results">
        @include('purchases._list_rows')
    </div>
</div>
