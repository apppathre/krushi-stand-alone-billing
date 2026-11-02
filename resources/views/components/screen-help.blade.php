@props(['topic' => 'stock'])

@php($help = \App\Support\InventoryHelp::get($topic))

<div class="screen-help no-print" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" class="screen-help__btn" @click="open = !open" :aria-expanded="open.toString()"
            title="{{ __('Help — when to use this screen') }}" aria-label="{{ __('Help') }}">
        <x-icon name="help" class="h-4 w-4" />
        <span class="screen-help__label">{{ __('Help') }}</span>
    </button>

    <div x-show="open" x-cloak x-transition
         class="screen-help__panel"
         role="dialog" :aria-hidden="(!open).toString()">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="screen-help__title">{{ $help['title'] }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ $help['summary'] }}</p>
            </div>
            <button type="button" class="btn-secondary btn-sm shrink-0" @click="open = false" aria-label="{{ __('Close') }}">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        @if (! empty($help['do']))
            <div class="mt-3">
                <p class="text-xs font-bold uppercase tracking-wide text-leaf-dark">{{ __('When / how to add') }}</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-ink">
                    @foreach ($help['do'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($help['dont']))
            <div class="mt-3">
                <p class="text-xs font-bold uppercase tracking-wide text-accent">{{ __('When not to use') }}</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-ink">
                    @foreach ($help['dont'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($help['tip']))
            <p class="mt-3 rounded-lg bg-sage/40 px-3 py-2 text-xs text-ink-soft">{{ $help['tip'] }}</p>
        @endif
    </div>
</div>
