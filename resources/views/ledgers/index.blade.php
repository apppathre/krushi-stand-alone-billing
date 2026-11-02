@php
    $groupLabels = [
        'cash' => __('Cash-in-Hand'),
        'bank' => __('Bank Accounts'),
        'tax' => __('Duties & Taxes (GST ledgers)'),
        'sales' => __('Sales Accounts'),
        'purchase' => __('Purchase Accounts'),
        'expense' => __('Indirect Expenses'),
        'income' => __('Indirect Incomes'),
        'discount' => __('Discount'),
        'round_off' => __('Round Off'),
        'capital' => __('Capital Account'),
        'other' => __('Other Accounts'),
        'client' => __('Clients'),
        'supplier' => __('Suppliers'),
        'both' => __('Client & Supplier'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __($k['title']) }}</h1>
            @if ($k['key'] === 'books')
                <p class="text-xs text-ink-soft">{{ __('Ledgers grouped by type. GST % rates are under Masters → Manage Item → GST Slabs.') }}</p>
            @endif
        </div>
    </x-slot>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Name, phone, GSTIN…')" class="w-full sm:w-64" />
        <x-select-field name="type" :label="__('Type')" :value="request('type')" :placeholder="__('All')"
            :options="collect($k['types'])->map(fn ($l) => __($l))->all()" class="w-full sm:w-44" />
        @if ($k['family'] === 'party' && isset($areas))
            <x-select-field name="area_id" :label="__('Area')" :value="request('area_id')" :placeholder="__('All areas')"
                :options="$areas->mapWithKeys(fn ($a) => [$a->id => $a->display_name])->all()" class="w-full sm:w-44" />
        @endif
        <x-select-field name="status" :label="__('Status')" :value="request('status')" :placeholder="__('All')"
            :options="['active' => __('Active'), 'inactive' => __('Inactive')]" class="w-full sm:w-36" />
        <x-slot:actions>
            @can('menu', [$k['key'], 'add'])
                @if ($k['key'] === 'clients')
                    @can('menu', ['areas', 'view'])
                        <a href="{{ route('areas.index') }}" class="btn-secondary btn-sm">{{ __('Areas') }}</a>
                    @endcan
                @endif
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route($k['key'].'.create') }}', '{{ __('Add :item', ['item' => __($k['singular'])]) }}', '{{ $k['family'] === 'party' ? '44rem' : '28rem' }}')">
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
                        <th>{{ __('Name') }}</th>
                        @if ($k['key'] !== 'books')
                            <th>{{ __('Type') }}</th>
                        @endif
                        @if ($k['family'] === 'party')
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Area') }}</th>
                            <th>{{ __('Village / City') }}</th>
                            <th>{{ __('GSTIN') }}</th>
                            <th class="num">{{ __('Credit') }}</th>
                            <th>{{ __('Salesperson') }}</th>
                        @endif
                        <th class="num">{{ __('Balance') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="num">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($k['key'] === 'books' && isset($groups))
                        @forelse ($groups as $type => $items)
                            <tr class="bg-sage/40">
                                <td colspan="4" class="py-2 text-xs font-semibold uppercase tracking-wide text-leaf-dark">
                                    {{ $groupLabels[$type] ?? __(ucwords(str_replace('_', ' ', $type))) }}
                                </td>
                            </tr>
                            @foreach ($items as $row)
                                @php($bal = $row->signedOpening() + (float) $row->dr_sum - (float) $row->cr_sum)
                                <tr>
                                    <td class="font-semibold">
                                        <a href="{{ route('reports.party-ledger', ['ledger_id' => $row->id]) }}" class="text-leaf hover:underline">{{ $row->display_name }}</a>
                                        @if ($row->is_system) <span class="badge-gray ml-1">{{ __('System') }}</span> @endif
                                    </td>
                                    <td class="num font-semibold">{{ money(abs($bal)) }} <span class="text-xs text-ink-soft">{{ $bal >= 0 ? __('Dr') : __('Cr') }}</span></td>
                                    <td><span class="{{ $row->is_active ? 'badge-green' : 'badge-gray' }}">{{ $row->is_active ? __('Active') : __('Inactive') }}</span></td>
                                    <td class="num whitespace-nowrap">
                                        @can('menu', [$k['key'], 'edit'])
                                            <button type="button" class="btn-secondary btn-sm"
                                                    onclick="KrushiDrawer.open('{{ route($k['key'].'.edit', $row) }}', '{{ __('Edit :item', ['item' => __($k['singular'])]) }}', '28rem')">
                                                {{ __('Edit') }}
                                            </button>
                                        @endcan
                                        @can('menu', [$k['key'], 'delete'])
                                            @unless ($row->is_system)
                                                <form method="POST" action="{{ route($k['key'].'.destroy', $row) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                                </form>
                                            @endunless
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                        @endforelse
                    @else
                        @forelse ($rows as $row)
                            @php($bal = $row->signedOpening() + (float) $row->dr_sum - (float) $row->cr_sum)
                            <tr>
                                <td class="font-semibold">
                                    <a href="{{ route('reports.party-ledger', ['ledger_id' => $row->id]) }}" class="text-leaf hover:underline">{{ $row->display_name }}</a>
                                    @if ($row->is_system) <span class="badge-gray ml-1">{{ __('System') }}</span> @endif
                                </td>
                                <td>{{ __(ucwords(str_replace('_', ' ', $row->type === 'both' ? 'Client & Supplier' : $row->type))) }}</td>
                                @if ($k['family'] === 'party')
                                    <td>{{ $row->phone }}</td>
                                    <td>{{ $row->area?->display_name }}</td>
                                    <td>{{ collect([$row->village, $row->city])->filter()->implode(', ') }}</td>
                                    <td class="font-mono text-xs">{{ $row->gstin }}</td>
                                    <td class="num text-xs">
                                        @if ($row->credit_limit !== null){{ money($row->credit_limit) }}@endif
                                        @if ($row->credit_days)<span class="block text-ink-soft">{{ $row->credit_days }} {{ __('days') }}</span>@endif
                                    </td>
                                    <td class="text-xs">{{ $row->salesperson?->name }}</td>
                                @endif
                                <td class="num font-semibold">{{ money(abs($bal)) }} <span class="text-xs text-ink-soft">{{ $bal >= 0 ? __('Dr') : __('Cr') }}</span></td>
                                <td><span class="{{ $row->is_active ? 'badge-green' : 'badge-gray' }}">{{ $row->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="num whitespace-nowrap">
                                    @can('menu', [$k['key'], 'edit'])
                                        <button type="button" class="btn-secondary btn-sm"
                                                onclick="KrushiDrawer.open('{{ route($k['key'].'.edit', $row) }}', '{{ __('Edit :item', ['item' => __($k['singular'])]) }}', '{{ $k['family'] === 'party' ? '44rem' : '28rem' }}')">
                                            {{ __('Edit') }}
                                        </button>
                                    @endcan
                                    @can('menu', [$k['key'], 'delete'])
                                        @unless ($row->is_system)
                                            <form method="POST" action="{{ route($k['key'].'.destroy', $row) }}" class="inline" onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                                @csrf @method('DELETE')
                                                <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                            </form>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
        @if ($k['key'] !== 'books' && method_exists($rows, 'links'))
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </div>
</x-app-layout>
