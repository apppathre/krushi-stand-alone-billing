{{-- Record a payment against a sale or purchase. Expects $action (route) and $doc (model with balance). --}}
@if (! $doc->isCancelled() && $doc->balance > 0.004)
    <form method="POST" action="{{ $action }}" class="card card-body no-print space-y-3">
        @csrf
        <h3 class="card-title">{{ $title }}</h3>
        <x-field name="amount" type="number" step="0.01" min="0.01" :max="$doc->balance" :label="__('Amount')" :value="$doc->balance" required />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @include('partials.payment-ledger-field')
            <x-field name="payment_date" type="date" :label="__('Date')" :value="old('payment_date', $voucherDate ?? now()->toDateString())" :min="optional($currentFinancialYear ?? null)?->start_date?->toDateString()" :max="optional($currentFinancialYear ?? null)?->end_date?->toDateString()" required />
        </div>
        <x-field name="reference" :label="__('Reference')" />
        <button class="btn-primary w-full">{{ __('Record payment') }}</button>
    </form>
@endif
