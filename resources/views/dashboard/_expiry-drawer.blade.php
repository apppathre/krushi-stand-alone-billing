<div class="space-y-3">
    <p class="text-sm text-ink-soft">
        {{ ($workScope ?? 'shop') === 'shop' ? __('Across all places in the shop') : __('For the selected place') }}
    </p>
    @forelse ($rows as $b)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-sage-dark/70 bg-sage/30 px-3 py-2.5">
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ $b->product->display_name }}</p>
                <p class="text-xs text-ink-soft">
                    {{ __('Batch') }} {{ $b->batch_no }} · {{ qty($b->qty_on_hand) }}
                    @if ($b->godown) · {{ $b->godown->display_name }} @endif
                </p>
            </div>
            <span class="{{ $b->expiry_date->isPast() ? 'badge-red' : 'badge-amber' }} shrink-0">{{ $b->expiry_date->format('d-m-Y') }}</span>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-ink-soft">{{ __('No batches are close to expiry.') }}</p>
    @endforelse
</div>
