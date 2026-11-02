@php
    $isPay = ($side ?? 'receivable') === 'payable';
@endphp
<div class="space-y-3">
    <div class="form-callout">
        <p class="form-callout__title">{{ $isPay ? __('Payments due') : __('Receipts due') }}</p>
        <p class="mb-0">
            {{ __('Total') }}:
            <strong>&#8377; {{ money($total) }}</strong>
            · {{ __(':count parties', ['count' => $rows->count()]) }}
        </p>
    </div>

    @forelse ($rows as $r)
        <div class="rounded-xl border border-sage-dark/70 bg-sage/30 px-3 py-2.5"
             x-data="partyOutreach(@js(route('party.outreach', $r->id)), { with_link: {{ $isPay ? 'false' : 'true' }} })">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ app()->getLocale() === 'mr' && ! empty($r->name_mr) ? $r->name_mr : $r->name }}</p>
                    <p class="text-xs text-ink-soft">{{ $r->phone ?: '—' }} · {{ __(ucfirst((string) $r->type)) }}</p>
                </div>
                <span class="{{ $isPay ? 'badge-amber' : 'badge-red' }} shrink-0 tabular-nums">
                    &#8377; {{ money(abs((float) $r->bal)) }}
                </span>
            </div>
            <div class="mt-2 flex flex-wrap gap-1">
                <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('call')">{{ __('Call') }}</button>
                <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="open('whatsapp')">{{ __('WhatsApp') }}</button>
                @unless ($isPay)
                    <label class="inline-flex items-center gap-1 text-[0.7rem] text-ink-soft">
                        <input type="checkbox" class="rounded border-sage-dark" x-model="withLink"> {{ __('Link') }}
                    </label>
                    <button type="button" class="btn-secondary btn-sm" :disabled="busy || !withLink" @click="open('copy')">{{ __('Copy link') }}</button>
                @endunless
            </div>
        </div>
    @empty
        <p class="py-8 text-center text-sm text-ink-soft">
            {{ $isPay ? __('No supplier balances due.') : __('No customer balances due.') }}
        </p>
    @endforelse

    @can('menu', ['report_outstanding', 'view'])
        <a href="{{ route('reports.outstanding', ['side' => $side]) }}" class="btn-secondary w-full">{{ __('Open full outstanding report') }}</a>
    @endcan
</div>
