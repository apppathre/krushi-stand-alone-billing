<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Purchase Order') }} {{ $order->order_no }}</h1>
    </x-slot>

    @if (session('status'))
        <div class="mb-3 rounded-lg border border-leaf/30 bg-leaf/10 px-3 py-2 text-sm no-print">{{ session('status') }}</div>
    @endif

    <div class="mb-3 flex flex-wrap gap-2 no-print">
        <a href="{{ route('purchase-orders.index') }}" class="btn-secondary btn-sm">{{ __('Back') }}</a>
        <button type="button" onclick="window.print()" class="btn-accent btn-sm">{{ __('Print') }}</button>
        @if ($order->isOpen())
            @if (auth()->user()->canMenu('purchase_orders', 'edit'))
                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn-secondary btn-sm">{{ __('Amend / Edit') }}</a>
            @endif
            @if (auth()->user()->canMenu('purchases', 'add'))
                <a href="{{ route('purchase-orders.convert', $order) }}" class="btn-primary btn-sm">{{ __('Convert to Purchase') }}</a>
            @endif
            @if (auth()->user()->canMenu('purchase_orders', 'delete'))
                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}"
                      onsubmit="return confirm('{{ __('Cancel this purchase order?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn-danger btn-sm">{{ __('Cancel / Delete') }}</button>
                </form>
            @endif
        @endif
        @if ($order->converted_purchase_id)
            <a href="{{ route('purchases.show', $order->converted_purchase_id) }}" class="btn-secondary btn-sm">{{ __('Open purchase') }}</a>
        @endif
    </div>

    <div class="card card-body mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-lg font-bold text-leaf-dark">{{ $order->supplier?->display_name }}</p>
                <p class="text-sm text-ink-soft">
                    {{ fmt_date($order->order_date) }}
                    · {{ $order->godown?->display_name }}
                    @if ($order->expected_date) · {{ __('Expected') }}: {{ fmt_date($order->expected_date) }} @endif
                    · {{ __(ucfirst($order->status)) }}
                </p>
            </div>
            <p class="text-2xl font-bold tabular-nums">&#8377; {{ money($order->grand_total) }}</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    <th class="num">{{ __('Rate') }}</th>
                    <th class="num">{{ __('GST %') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $line)
                    <tr>
                        <td class="font-semibold">{{ $line->product?->display_name }}
                            <span class="block text-xs font-normal text-ink-soft">{{ $line->product?->unit?->short_name }} · {{ $line->hsn_code }}</span>
                        </td>
                        <td class="num">{{ qty($line->qty) }}</td>
                        <td class="num">{{ money($line->rate) }}</td>
                        <td class="num">{{ qty($line->gst_rate) }}</td>
                        <td class="num font-semibold">{{ money($line->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($order->notes)
        <p class="mt-3 text-sm text-ink-soft">{{ $order->notes }}</p>
    @endif
</x-app-layout>
