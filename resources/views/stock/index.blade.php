<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ request()->boolean('low') ? __('Low Stock Alerts') : __('Stock') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="request()->boolean('low') ? __('Low Stock Alerts') : __('Stock')">
        <a href="{{ route('stock.preview', ['godown_id' => $gid]) }}" class="btn-secondary btn-sm">{{ __('Stock preview') }}</a>
        <x-screen-help topic="stock" />
    </x-page-head>

    @if ($lowCount > 0 && ! request()->boolean('low'))
        <div class="mb-4 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="flex items-center gap-2"><x-icon name="alert" class="h-4 w-4" /> {{ __(':count items low on stock', ['count' => $lowCount]) }}</span>
            <a href="{{ route('stock.low', ['godown_id' => $gid]) }}" class="font-bold underline">{{ __('Show low stock') }}</a>
        </div>
    @endif

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Product name or code…')" class="w-64" />
        <x-select-field name="godown_id" :label="__('Godown')" :value="$gid" :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-44" />
        <x-select-field name="category_id" :label="__('Category')" :value="request('category_id')" :placeholder="__('All')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-44" />
        <div class="pb-2">
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="low" value="1" class="checkbox" @checked(request()->boolean('low'))> {{ __('Low stock only') }}
            </label>
        </div>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th class="num">{{ __('On hand') }}</th>
                        <th class="num">{{ __('Reorder level') }}</th>
                        <th>{{ __('Batches (batch · expiry · qty)') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $p)
                        @php($low = $p->reorder_level > 0 && $p->on_hand <= $p->reorder_level)
                        <tr>
                            <td class="font-semibold">{{ $p->display_name }} <span class="block text-xs font-normal text-ink-soft">{{ $p->code }}</span></td>
                            <td>{{ $p->category?->display_name }}</td>
                            <td class="num font-semibold {{ $low ? 'text-red-700' : '' }}">{{ qty($p->on_hand) }} {{ $p->unit?->short_name }}</td>
                            <td class="num">{{ qty($p->reorder_level) }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($p->batches as $b)
                                        @php($expired = $b->expiry_date && $b->expiry_date->isPast())
                                        @php($soon = $b->expiry_date && ! $expired && $b->expiry_date->lte(now()->addDays(60)))
                                        <span class="{{ $expired ? 'badge-red' : ($soon ? 'badge-amber' : 'badge-green') }}">
                                            {{ $b->batch_no }}@if ($b->expiry_date) &middot; {{ $b->expiry_date->format('m/Y') }}@endif &middot; {{ qty($b->qty_on_hand) }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-ink-soft">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="num">
                                @if ($low) <span class="badge-red">{{ __('Low') }}</span> @endif
                                <a href="{{ route('stock.movements', [$p, 'godown_id' => $gid]) }}" class="btn-secondary btn-sm">{{ __('History') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
