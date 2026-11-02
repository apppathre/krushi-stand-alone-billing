<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Price List') }}</h1>
    </x-slot>

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <a href="{{ route('reports.price-list', ['tab' => 'browse'] + request()->except('tab', 'page')) }}"
           class="btn-sm {{ ($tab ?? 'browse') === 'browse' ? 'btn-primary' : 'btn-secondary' }}">{{ __('Browse') }}</a>
        @if (auth()->user()->canMenu('products', 'edit') || auth()->user()->canMenu('report_price_list', 'edit'))
            <a href="{{ route('reports.price-list', ['tab' => 'manage'] + request()->except('tab', 'page')) }}"
               class="btn-sm {{ ($tab ?? '') === 'manage' ? 'btn-primary' : 'btn-secondary' }}">{{ __('Manage prices') }}</a>
        @endif
        <a href="{{ route('reports.price-list', ['tab' => 'log'] + request()->except('tab', 'page')) }}"
           class="btn-sm {{ ($tab ?? '') === 'log' ? 'btn-primary' : 'btn-secondary' }}">{{ __('Price log') }}</a>
    </div>

    @if (($tab ?? 'browse') === 'browse')
        <x-auto-filter>
            <x-select-field name="category_id" :label="__('Category')" :value="$categoryId" :placeholder="__('All')"
                :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-full sm:w-48" />
            <x-select-field name="manufacturer_id" :label="__('Manufacturer')" :value="$manufacturerId" :placeholder="__('All')"
                :options="$manufacturers->mapWithKeys(fn ($m) => [$m->id => $m->display_name])->all()" class="w-full sm:w-48" />
            <input type="hidden" name="tab" value="browse">
            <button type="button" onclick="window.print()" class="btn-secondary no-print">{{ __('Print') }}</button>
        </x-auto-filter>

        <div id="list-results">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th><th>{{ __('Item') }}</th><th>{{ __('Manufacturer') }}</th>
                            <th>{{ __('HSN') }}</th><th class="num">{{ __('GST %') }}</th>
                            <th class="num">{{ __('MRP') }}</th><th class="num">{{ __('Sale rate') }}</th><th>{{ __('Unit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td>{{ $r->code }}</td>
                                <td class="font-semibold">{{ app()->getLocale() === 'mr' && $r->name_mr ? $r->name_mr : $r->name }}
                                    <span class="block text-xs font-normal text-ink-soft">{{ $r->category_name }}</span></td>
                                <td>{{ $r->manufacturer_name }}</td>
                                <td>{{ $r->hsn_code }}</td>
                                <td class="num">{{ qty($r->gst_rate) }}</td>
                                <td class="num">{{ money($r->mrp) }}</td>
                                <td class="num font-semibold">{{ money($r->sale_rate) }}</td>
                                <td>{{ $r->unit }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif (($tab ?? '') === 'manage')
        <x-auto-filter>
            <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Product…')" class="w-full sm:w-56" />
            <x-select-field name="category_id" :label="__('Category')" :value="$categoryId" :placeholder="__('All')"
                :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-full sm:w-44" />
            <x-select-field name="manufacturer_id" :label="__('Manufacturer')" :value="$manufacturerId" :placeholder="__('All')"
                :options="$manufacturers->mapWithKeys(fn ($m) => [$m->id => $m->display_name])->all()" class="w-full sm:w-44" />
            <input type="hidden" name="tab" value="manage">
        </x-auto-filter>

        <form method="POST" action="{{ route('reports.price-list.update') }}" class="space-y-3" data-busy-label="{{ __('Saving…') }}">
            @csrf
            <div class="card card-body flex flex-wrap items-end gap-3">
                <x-field name="note" :label="__('Change note (optional)')" :value="old('note')" class="w-full sm:w-80" :placeholder="__('e.g. April rate revision')" />
                <button class="btn-primary">{{ __('Save changed prices') }}</button>
            </div>
            <div id="list-results" class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="num">{{ __('Purchase') }}</th>
                            <th class="num">{{ __('MRP') }}</th>
                            <th class="num">{{ __('Sale rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $i => $p)
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $p->id }}">
                                    <span class="font-semibold">{{ $p->display_name }}</span>
                                    <span class="block text-xs text-ink-soft">{{ $p->code }} · {{ $p->unit?->short_name }}</span>
                                </td>
                                <td class="num"><input type="number" step="0.01" min="0" name="rows[{{ $i }}][purchase_rate]" value="{{ old('rows.'.$i.'.purchase_rate', $p->purchase_rate) }}" class="input input-sm w-28 text-right"></td>
                                <td class="num"><input type="number" step="0.01" min="0" name="rows[{{ $i }}][mrp]" value="{{ old('rows.'.$i.'.mrp', $p->mrp) }}" class="input input-sm w-28 text-right"></td>
                                <td class="num"><input type="number" step="0.01" min="0" name="rows[{{ $i }}][sale_rate]" value="{{ old('rows.'.$i.'.sale_rate', $p->sale_rate) }}" class="input input-sm w-28 text-right font-semibold"></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $products->links() }}</div>
        </form>
    @else
        <x-auto-filter>
            <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Product…')" class="w-full sm:w-64" />
            <input type="hidden" name="tab" value="log">
        </x-auto-filter>
        <div id="list-results" class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Field') }}</th>
                        <th class="num">{{ __('Old') }}</th>
                        <th class="num">{{ __('New') }}</th>
                        <th>{{ __('By') }}</th>
                        <th>{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-xs">{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                            <td class="font-semibold">{{ $log->product?->display_name }}
                                <span class="block text-xs font-normal text-ink-soft">{{ $log->product?->code }}</span></td>
                            <td>{{ __($log->field === 'sale_rate' ? 'Sale rate' : ($log->field === 'mrp' ? 'MRP' : 'Purchase rate')) }}</td>
                            <td class="num">{{ money($log->old_value) }}</td>
                            <td class="num font-semibold text-leaf-dark">{{ money($log->new_value) }}</td>
                            <td class="text-sm">{{ $log->user?->name ?? '—' }}</td>
                            <td class="text-xs text-ink-soft">{{ $log->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-8 text-center text-ink-soft">{{ __('No price changes logged yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
    @endif
</x-app-layout>
