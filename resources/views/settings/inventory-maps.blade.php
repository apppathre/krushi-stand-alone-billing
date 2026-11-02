<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Inventory Map') }}</h1>
    </x-slot>

    <p class="mb-4 max-w-3xl text-sm text-ink-soft">
        {{ __('Override the default sales / purchase ledger for a whole category or a single product. A product override wins over its category, which wins over the Account Map.') }}
    </p>

    @can('menu', ['settings_inventory_maps', 'add'])
        <form method="POST" action="{{ route('settings.inventory-maps.store') }}" x-data="{ scope: '{{ old('scope', 'category') }}' }" class="card card-body mb-6 grid max-w-5xl items-end gap-4 md:grid-cols-5">
            @csrf
            <div>
                <label class="label" for="scope">{{ __('Applies to') }}</label>
                <select id="scope" name="scope" x-model="scope" class="input">
                    <option value="category">{{ __('Category') }}</option>
                    <option value="product">{{ __('Product') }}</option>
                </select>
            </div>
            <div x-show="scope === 'category'">
                <x-select-field name="category_id" :label="__('Category')" :placeholder="__('Select')"
                    :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" />
            </div>
            <div x-show="scope === 'product'" x-cloak>
                <x-select-field name="product_id" :label="__('Product')" :placeholder="__('Select')"
                    :options="$products->mapWithKeys(fn ($p) => [$p->id => $p->display_name])->all()" />
            </div>
            <x-select-field name="sales_ledger_id" :label="__('Sales ledger')" :placeholder="__('Default')"
                :options="$salesLedgers->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all()" />
            <x-select-field name="purchase_ledger_id" :label="__('Purchase ledger')" :placeholder="__('Default')"
                :options="$purchaseLedgers->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all()" />
            <button class="btn-primary">{{ __('Save override') }}</button>
        </form>
    @endcan

    <div class="table-wrap max-w-5xl">
        <table class="table">
            <thead><tr><th>{{ __('Scope') }}</th><th>{{ __('Applies to') }}</th><th>{{ __('Sales ledger') }}</th><th>{{ __('Purchase ledger') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($maps as $m)
                    <tr>
                        <td><span class="badge-green">{{ $m->scope === 'category' ? __('Category') : __('Product') }}</span></td>
                        <td class="font-semibold">{{ $m->scope === 'category' ? $m->category?->display_name : $m->product?->display_name }}</td>
                        <td>{{ $m->salesLedger?->display_name ?? '—' }}</td>
                        <td>{{ $m->purchaseLedger?->display_name ?? '—' }}</td>
                        <td class="num">
                            @can('menu', ['settings_inventory_maps', 'delete'])
                                <form method="POST" action="{{ route('settings.inventory-maps.destroy', $m) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-soft">{{ __('No overrides yet. All items use the Account Map defaults.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
