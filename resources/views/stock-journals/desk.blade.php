<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="text-xs text-ink-soft">{{ $blurb }}</p>
        </div>
    </x-slot>

    <div class="form-callout mb-4" role="note">
        <p class="form-callout__title">{{ __('Stock Journal') }}</p>
        <p class="mb-0">{{ __('Entry screen for this voucher type is being wired like TMS. Opening stock is available now under the same menu group.') }}</p>
    </div>

    <div class="card card-body">
        <div class="flex flex-wrap gap-2">
            @foreach ($types as $key => [$en])
                <a href="{{ route('stock-journals.'.$key) }}"
                   class="btn-sm {{ $type === $key ? 'btn-primary' : 'btn-secondary' }}">
                    {{ __($en) }}
                </a>
            @endforeach
            <a href="{{ route('opening-stock.index') }}" class="btn-secondary btn-sm">{{ __('Opening Stock') }}</a>
        </div>
        <p class="mt-4 text-sm text-ink-soft">{{ __('Use Opening Stock for as-on balances. Other stock journals will post here with the same form-first + list drawer pattern as Purchase / Sale.') }}</p>
    </div>
</x-app-layout>
