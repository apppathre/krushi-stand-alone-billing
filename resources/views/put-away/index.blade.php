<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Put Away') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Put Away')" :blurb="__('Place batches on a rack after receive. Qty stays by place.')">
        <x-screen-help topic="put_away" />
    </x-page-head>

    <x-auto-filter>
        <div>
            <label class="label" for="f-godown_id">{{ __('Place / Godown') }}</label>
            <select id="f-godown_id" name="godown_id" class="input w-full sm:w-56" onchange="this.form.submit()">
                @foreach ($places as $g)
                    <option value="{{ $g->id }}" @selected((int) $placeId === (int) $g->id)>{{ $g->display_name }}</option>
                @endforeach
            </select>
        </div>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Product or batch…')" class="w-full sm:w-56" />
        <x-select-field name="placed" :label="__('Placement')" :value="request('placed')" :placeholder="__('All')"
            :options="['no' => __('Not placed'), 'yes' => __('Placed')]" class="w-full sm:w-40" />
        <x-slot:actions>
            @can('menu', ['racks', 'add'])
                <a href="{{ route('racks.index') }}" class="btn-secondary btn-sm">{{ __('Manage racks') }}</a>
            @endcan
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Batch') }}</th>
                        <th class="num">{{ __('Qty') }}</th>
                        <th>{{ __('Rack / aisle') }}</th>
                        <th class="num">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $row->product?->display_name }}</div>
                                <div class="text-xs text-ink-soft">{{ $row->product?->code }}</div>
                            </td>
                            <td>
                                {{ $row->batch_no }}
                                @if ($row->expiry_date)
                                    <div class="text-xs text-ink-soft">{{ __('Exp') }} {{ $row->expiry_date->format('d-m-Y') }}</div>
                                @endif
                            </td>
                            <td class="num">{{ qty($row->qty_live) }} {{ $row->product?->unit?->short_name }}</td>
                            <td>
                                @if ($row->rack)
                                    <span class="badge-green">{{ $row->rack->label }}</span>
                                @else
                                    <span class="badge-gray">{{ __('Not placed') }}</span>
                                @endif
                            </td>
                            <td class="num">
                                @can('menu', ['put_away', 'edit'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route('put-away.edit', $row) }}', '{{ __('Place stock') }}', '28rem')">
                                        {{ $row->rack_id ? __('Change') : __('Place') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-soft">{{ __('No stock batches in this place.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
