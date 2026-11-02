<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Products') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Name, code, HSN…')" class="w-full sm:w-64" />
        <x-select-field name="category_id" :label="__('Category')" :value="request('category_id')" :placeholder="__('All')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-full sm:w-44" />
        <x-select-field name="manufacturer_id" :label="__('Manufacturer')" :value="request('manufacturer_id')" :placeholder="__('All')"
            :options="$manufacturers->mapWithKeys(fn ($m) => [$m->id => $m->display_name])->all()" class="w-full sm:w-48" />
        <x-select-field name="status" :label="__('Status')" :value="request('status')" :placeholder="__('All')"
            :options="['active' => __('Active'), 'inactive' => __('Inactive')]" class="w-full sm:w-36" />
        <x-slot:actions>
            @can('menu', ['products', 'add'])
                <a href="{{ route('products.bulk') }}" class="btn-secondary btn-sm">
                    <x-icon name="list" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Bulk / Excel') }}</span>
                </a>
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('products.create') }}', '{{ __('Add :item', ['item' => __('Product')]) }}', '40rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add') }}</span>
                </button>
            @endcan
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Manufacturer') }}</th>
                        <th>{{ __('Packing') }}</th>
                        <th>{{ __('HSN') }}</th>
                        <th class="num">{{ __('GST %') }}</th>
                        <th class="num">{{ __('Purchase') }}</th>
                        <th class="num">{{ __('Sale') }}</th>
                        <th class="num">{{ __('MRP') }}</th>
                        <th>{{ __('Batch') }}</th>
                        <th class="num">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        <tr>
                            <td>{{ $p->code }}</td>
                            <td class="font-semibold">
                                {{ $p->display_name }}
                                @unless ($p->is_active) <span class="badge-gray ml-1">{{ __('Inactive') }}</span> @endunless
                                <span class="block text-xs font-normal text-ink-soft">{{ $p->unit?->display_name }}</span>
                            </td>
                            <td>{{ $p->category?->display_name }}</td>
                            <td>{{ $p->manufacturer?->display_name }}</td>
                            <td>{{ $p->packing }}</td>
                            <td>{{ $p->hsn_code }}</td>
                            <td class="num">{{ qty($p->gst_rate) }}</td>
                            <td class="num">{{ money($p->purchase_rate) }}</td>
                            <td class="num">{{ money($p->sale_rate) }}</td>
                            <td class="num">{{ money($p->mrp) }}</td>
                            <td>{!! $p->batch_required ? '<span class="badge-green">'.e(__('Yes')).'</span>' : '<span class="badge-gray">'.e(__('No')).'</span>' !!}</td>
                            <td class="num whitespace-nowrap">
                                @can('menu', ['products', 'edit'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route('products.edit', $p) }}', '{{ __('Edit :item', ['item' => __('Product')]) }}', '40rem')">
                                        {{ __('Edit') }}
                                    </button>
                                @endcan
                                @can('menu', ['products', 'delete'])
                                    <form method="POST" action="{{ route('products.destroy', $p) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
