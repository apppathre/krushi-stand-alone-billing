<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase Returns') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Purchase Returns')">
        <x-screen-help topic="purchase_return" />
    </x-page-head>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" :placeholder="__('Return no, purchase, supplier…')" class="w-full sm:w-64" />
        <x-field name="from" type="date" :label="__('From')" :value="$from" class="w-full sm:w-40" />
        <x-field name="to" type="date" :label="__('To')" :value="$to" class="w-full sm:w-40" />
        <x-select-field name="godown_id" :label="__('Place')" :value="request('godown_id')" :placeholder="__('All')"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-full sm:w-40" />
        <x-slot:actions>
            @can('menu', ['purchase_returns', 'add'])
                <a href="{{ route('purchase-returns.create') }}" class="btn-primary btn-sm">
                    <x-icon name="plus" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Add') }}</span>
                </a>
            @endcan
        </x-slot:actions>
    </x-auto-filter>

    <div id="list-results">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>{{ __('Return no') }}</th><th>{{ __('Date') }}</th><th>{{ __('Purchase') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Place') }}</th><th>{{ __('Reason') }}</th><th class="num">{{ __('Amount') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td><a href="{{ route('purchase-returns.show', $r) }}" class="font-semibold text-leaf hover:underline">{{ $r->return_no }}</a></td>
                            <td>{{ fmt_date($r->return_date) }}</td>
                            <td>{{ $r->purchase->purchase_no }}</td>
                            <td>{{ $r->supplier->display_name }}</td>
                            <td>{{ $r->godown->display_name }}</td>
                            <td>{{ $r->reason }}</td>
                            <td class="num">{{ money($r->grand_total) }}</td>
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
