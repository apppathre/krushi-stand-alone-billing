<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Stock History') }}: {{ $product->display_name }}</h1>
    </x-slot>

    <x-auto-filter>
        <x-select-field name="godown_id" :label="__('Godown')" :value="$gid" :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-48" />
        <a href="{{ route('stock.index', ['godown_id' => $gid]) }}" class="btn-secondary">{{ __('Back to stock') }}</a>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Batch') }}</th>
                        <th class="num">{{ __('In') }}</th><th class="num">{{ __('Out') }}</th><th class="num">{{ __('Cost rate') }}</th><th>{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $m)
                        <tr>
                            <td>{{ fmt_date($m->movement_date) }}</td>
                            <td><span class="badge-green">{{ __(ucwords(str_replace('_', ' ', $m->movement_type))) }}</span></td>
                            <td>{{ $m->batch?->batch_no }}</td>
                            <td class="num">{{ $m->qty_in > 0 ? qty($m->qty_in) : '' }}</td>
                            <td class="num">{{ $m->qty_out > 0 ? qty($m->qty_out) : '' }}</td>
                            <td class="num">{{ money($m->rate) }}</td>
                            <td>{{ $m->narration }}</td>
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
