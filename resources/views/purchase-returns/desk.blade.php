<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Purchase Return') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Return goods to supplier against a purchase bill.') }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Coming next') }}</p>
        <p class="mb-0">{{ __('Purchase return entry (form first + recent list in drawer) will mirror Sales Returns and TMS purchase books.') }}</p>
    </div>

    <div class="card card-body flex flex-wrap gap-2">
        <a href="{{ route('purchases.index') }}" class="btn-primary btn-sm">{{ __('Go to Purchase') }}</a>
        <a href="{{ route('sale-returns.index') }}" class="btn-secondary btn-sm">{{ __('Sales Returns') }}</a>
    </div>
</x-app-layout>
