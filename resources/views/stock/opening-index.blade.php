<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Opening Stock') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Opening Stock')" :blurb="__('Go-live qty — stock in only, no money.')">
        <x-screen-help topic="opening_stock" />
    </x-page-head>

    <x-auto-filter>
        <x-field name="q" :label="__('Product')" :value="request('q')" :placeholder="__('Type to search…')" class="w-full sm:w-64" />
        <x-slot:actions>
            @can('menu', ['opening_stock', 'add'])
                <a href="{{ route('opening-stock.bulk') }}" class="btn-primary btn-sm">
                    <x-icon name="list" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Bulk update') }}</span>
                </a>
                <button type="button" class="btn-secondary btn-sm"
                        onclick="KrushiDrawer.open('{{ route('opening-stock.create', ['drawer' => 1]) }}', '{{ __('Add opening stock') }}', '36rem')">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add one') }}</span>
                </button>
            @endcan
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th><th>{{ __('Godown') }}</th><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th>
                        <th>{{ __('Expiry') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $m)
                        <tr>
                            <td>{{ fmt_date($m->movement_date) }}</td>
                            <td>{{ $m->godown?->display_name }}</td>
                            <td class="font-semibold">{{ $m->product?->display_name }}</td>
                            <td>{{ $m->batch?->batch_no }}</td>
                            <td>{{ fmt_date($m->batch?->expiry_date) }}</td>
                            <td class="num">{{ qty($m->qty_in) }}</td>
                            <td class="num">{{ money($m->rate) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
