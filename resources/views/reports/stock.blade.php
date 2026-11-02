<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title flex items-center gap-2"><x-icon name="stock" class="h-7 w-7 text-leaf" /> {{ __('Stock Report') }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-select-field name="godown_id" :label="__('Godown')" :value="$godownId"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-full sm:w-48" />
        <x-select-field name="category_id" :label="__('Category')" :value="$categoryId" :placeholder="__('All')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-full sm:w-48" />
        <x-slot:actions>
            <x-report-export :formats="['print']" />
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="mb-3 flex flex-wrap gap-4 text-sm">
            <span class="badge-green">{{ __('Qty') }}: {{ qty($qty_total) }}</span>
            <span class="badge-amber">{{ __('Value') }}: &#8377; {{ money($value_total) }}</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th><th>{{ __('Item') }}</th><th>{{ __('Category') }}</th>
                        <th class="num">{{ __('Qty') }}</th><th>{{ __('Unit') }}</th>
                        <th class="num">{{ __('Avg cost') }}</th><th class="num">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $r->code }}</td>
                            <td class="font-semibold">{{ app()->getLocale() === 'mr' && $r->name_mr ? $r->name_mr : $r->name }}</td>
                            <td>{{ $r->category_name }}</td>
                            <td class="num {{ $r->qty <= 0 ? 'text-red-700' : '' }}">{{ qty($r->qty) }}</td>
                            <td>{{ $r->unit }}</td>
                            <td class="num">{{ money($r->avg_cost ?? 0) }}</td>
                            <td class="num">{{ money($r->value) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
