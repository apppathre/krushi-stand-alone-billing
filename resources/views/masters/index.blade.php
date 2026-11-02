<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __($c['title']) }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Type to search…')" class="w-full sm:w-64" />
        <x-select-field name="status" :label="__('Status')" :value="request('status')" :placeholder="__('All')"
            :options="['active' => __('Active'), 'inactive' => __('Inactive')]" class="w-full sm:w-40" />
        <x-slot:actions>
            @can('menu', [$c['menu'], 'add'])
                <button type="button" class="btn-primary btn-sm"
                        onclick="KrushiDrawer.open('{{ route($c['route'].'.create') }}', '{{ __('Add :item', ['item' => __($c['singular'])]) }}', '28rem')">
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
                        @foreach ($c['columns'] as $col)
                            <th>{{ __($col['label']) }}</th>
                        @endforeach
                        <th>{{ __('Status') }}</th>
                        <th class="num">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($c['columns'] as $col)
                                <td>{{ $row->{$col['name']} }}</td>
                            @endforeach
                            <td><span class="{{ $row->is_active ? 'badge-green' : 'badge-gray' }}">{{ $row->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="num whitespace-nowrap">
                                @can('menu', [$c['menu'], 'edit'])
                                    <button type="button" class="btn-secondary btn-sm"
                                            onclick="KrushiDrawer.open('{{ route($c['route'].'.edit', $row) }}', '{{ __('Edit :item', ['item' => __($c['singular'])]) }}', '28rem')">
                                        {{ __('Edit') }}
                                    </button>
                                @endcan
                                @can('menu', [$c['menu'], 'delete'])
                                    <form method="POST" action="{{ route($c['route'].'.destroy', $row) }}" class="inline"
                                          onsubmit="return confirm('{{ __('Delete this record?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn-danger btn-sm">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($c['columns']) + 2 }}" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
