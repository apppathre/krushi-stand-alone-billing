@props(['sale'])

@if ($sale->status === 'cancelled')
    <span class="badge-gray">{{ __('Cancelled') }}</span>
@else
    @switch($sale->payment_status)
        @case('paid') <span class="badge-green">{{ __('Paid') }}</span> @break
        @case('partial') <span class="badge-amber">{{ __('Partial') }}</span> @break
        @default <span class="badge-red">{{ __('Unpaid') }}</span>
    @endswitch
@endif
