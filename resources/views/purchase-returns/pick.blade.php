<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('New purchase return') }}</h1>
        <p class="text-xs text-ink-soft">{{ __('Step 1: choose the purchase bill to return goods against.') }}</p>
    </x-slot>

    <x-auto-filter>
        <x-field name="q" :label="__('Search purchase')" :value="request('q')" :placeholder="__('Purchase no, supplier invoice…')" class="w-full max-w-md" />
    </x-auto-filter>

    <div id="list-results">
        <div class="txn-pick-list">
            @forelse ($purchases as $p)
                <a href="{{ route('purchase-returns.create', ['purchase_id' => $p->id]) }}" class="txn-pick-card">
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $p->purchase_no }}</p>
                        <p class="text-xs text-ink-soft truncate">{{ fmt_date($p->purchase_date) }} · {{ $p->supplier->display_name }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-semibold">{{ money($p->grand_total) }}</p>
                        <span class="text-xs font-semibold text-leaf-dark">{{ __('Select') }} →</span>
                    </div>
                </a>
            @empty
                <p class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</p>
            @endforelse
        </div>

        <div class="table-wrap hidden lg:block">
            <table class="table">
                <thead><tr><th>{{ __('Purchase') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th class="num">{{ __('Total') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($purchases as $p)
                        <tr>
                            <td class="font-semibold">{{ $p->purchase_no }}</td>
                            <td>{{ fmt_date($p->purchase_date) }}</td>
                            <td>{{ $p->supplier->display_name }}</td>
                            <td class="num">{{ money($p->grand_total) }}</td>
                            <td class="num"><a href="{{ route('purchase-returns.create', ['purchase_id' => $p->id]) }}" class="btn-primary btn-sm">{{ __('Select') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-ink-soft">{{ __('No records found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $purchases->links() }}</div>
    </div>
</x-app-layout>
