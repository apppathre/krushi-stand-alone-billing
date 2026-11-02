<div class="space-y-3">
    <p class="text-sm text-ink-soft">
        {{ ($workScope ?? 'shop') === 'shop' ? __('Across all places in the shop') : __('For the selected place') }}
    </p>
    @forelse ($rows as $p)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-sage-dark/70 bg-sage/30 px-3 py-2.5">
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ app()->getLocale() === 'mr' && $p->name_mr ? $p->name_mr : $p->name }}</p>
                <p class="text-xs text-ink-soft">{{ __('Reorder level') }}: {{ qty($p->reorder_level) }}</p>
            </div>
            <span class="badge-red shrink-0">{{ qty($p->on_hand) }}</span>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-ink-soft">{{ __('All products are above their reorder level.') }}</p>
    @endforelse
    @can('menu', ['stock', 'view'])
        <a href="{{ route('stock.low') }}" class="btn-secondary w-full">{{ __('Open full stock list') }}</a>
    @endcan
</div>
