<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase return against') }} {{ $purchase->purchase_no }}</h1>
        <p class="text-xs text-ink-soft">{{ $purchase->supplier->display_name }} &middot; {{ fmt_date($purchase->purchase_date) }}</p>
    </x-slot>

    <form method="POST" action="{{ route('purchase-returns.store') }}" class="txn-desk space-y-4">
        @csrf
        <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">

        <div class="card card-body grid gap-4 sm:grid-cols-2 md:grid-cols-3">
            <x-field name="return_date" type="date" :label="__('Return date')" :value="old('return_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
            @include('partials.voucher-number', [
                'docType' => 'purchase_return',
                'dateInput' => '[name=return_date]',
                'numberFormats' => $numberFormats ?? [],
            ])
            <x-field name="reason" :label="__('Reason')" class="sm:col-span-2 md:col-span-3" />
        </div>

        <div class="txn-lines">
            <div class="txn-line-head txn-return-head hidden lg:grid">
                <span>{{ __('Product') }}</span>
                <span>{{ __('Batch') }}</span>
                <span class="text-right">{{ __('Bought') }}</span>
                <span class="text-right">{{ __('Returned') }}</span>
                <span class="text-right">{{ __('Rate') }}</span>
                <span class="text-right">{{ __('GST %') }}</span>
                <span>{{ __('Return qty') }}</span>
            </div>
            @foreach ($purchase->items as $it)
                <div class="txn-line">
                    <div class="txn-return-row">
                        <div class="txn-line__full lg:contents">
                            <p class="font-semibold text-ink">{{ $it->product->display_name }}</p>
                            <p class="txn-line__meta lg:hidden">
                                @php $batchLabel = $it->batch && $it->batch->batch_no !== 'NA' ? $it->batch->batch_no : ($it->batch_no ?? ''); @endphp
                                @if ($batchLabel){{ __('Batch') }} {{ $batchLabel }} · @endif
                                {{ __('Bought') }} {{ qty($it->qty) }} · {{ __('Returned') }} {{ qty($it->returned_qty ?? 0) }}
                                · {{ money($it->rate) }} · GST {{ qty($it->gst_rate) }}%
                            </p>
                        </div>
                        <div class="hidden lg:block text-sm">
                            {{ $it->batch && $it->batch->batch_no !== 'NA' ? $it->batch->batch_no : ($it->batch_no ?? '—') }}
                        </div>
                        <div class="hidden lg:block num text-sm">{{ qty($it->qty) }}</div>
                        <div class="hidden lg:block num text-sm">{{ qty($it->returned_qty ?? 0) }}</div>
                        <div class="hidden lg:block num text-sm">{{ money($it->rate) }}</div>
                        <div class="hidden lg:block num text-sm">{{ qty($it->gst_rate) }}</div>
                        <div>
                            <label class="txn-line__label label">{{ __('Return qty') }}</label>
                            @if ($it->returnable_qty > 0)
                                <input type="number" inputmode="decimal" step="0.001" min="0" max="{{ $it->returnable_qty }}" name="qty[{{ $it->id }}]"
                                       value="{{ old('qty.'.$it->id, 0) }}" class="input input-sm w-full lg:w-28">
                            @else
                                <span class="text-xs text-ink-soft">{{ __('Fully returned') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @foreach ($errors->get('items') as $m) <p class="error-text">{{ $m }}</p> @endforeach
        @foreach ($errors->get('qty.*') as $messages) @foreach ($messages as $m) <p class="error-text">{{ $m }}</p> @endforeach @endforeach

        <div class="txn-sticky-actions flex flex-wrap gap-2">
            <button class="btn-primary flex-1 sm:flex-none">{{ __('Save purchase return') }}</button>
            <a href="{{ route('purchases.show', $purchase) }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
