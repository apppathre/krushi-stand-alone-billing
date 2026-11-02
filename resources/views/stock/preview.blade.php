<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Stock preview') }}</h1>
    </x-slot>

    <x-page-head class="mb-3" :title="__('Stock preview')" :blurb="__('Large view for the counter. For a TV without login, use Live stock + passkey.')">
        <a href="{{ route('stock.index', ['godown_id' => $gid]) }}" class="btn-secondary btn-sm">{{ __('Stock list') }}</a>
        @can('menu', ['products', 'edit'])
            <button type="button" class="btn-secondary btn-sm"
                    onclick="KrushiDrawer.open('{{ route('products.live-board') }}', '{{ __('Live screen items') }}', '52rem')">
                <x-icon name="stock" class="h-4 w-4" /> {{ __('Live screen items') }}
            </button>
        @endcan
        @if (auth()->user()->canMenu('settings_app_control', 'view'))
            <a href="{{ route('settings.app-control') }}#live-stock" class="btn-secondary btn-sm">{{ __('Live stock settings') }}</a>
        @endif
    </x-page-head>

    <x-auto-filter>
        <x-field name="q" :label="__('Search')" :value="request('q')" class="w-full sm:w-56" />
        <x-select-field name="godown_id" :label="__('Godown')" :value="$gid"
            :options="$godowns->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" class="w-full sm:w-44" />
        <x-select-field name="category_id" :label="__('Category')" :value="request('category_id')" :placeholder="__('All')"
            :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->display_name])->all()" class="w-full sm:w-44" />
        <div class="pb-2">
            <label class="inline-flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="instock" value="1" class="checkbox" @checked(request()->boolean('instock'))> {{ __('In stock only') }}
            </label>
        </div>
    </x-auto-filter>

    <p class="mb-3 text-sm text-ink-soft">{{ $godownName }} · {{ now()->format('d-m-Y H:i') }}</p>

    <div id="list-results" class="stock-preview-grid">
        @forelse ($items as $it)
            <article class="stock-preview-card {{ $it->low ? 'is-low' : ($it->qty <= 0 ? 'is-empty' : '') }}">
                <h3>{{ $it->name }}</h3>
                <p class="meta">{{ $it->code }}@if ($it->category) · {{ $it->category }}@endif</p>
                <p class="qty">{{ qty($it->qty) }} <span>{{ $it->unit }}</span></p>
                <p class="rate">₹ {{ money($it->rate) }}</p>
                @if ($it->low)<span class="pill">{{ __('Low') }}</span>@endif
            </article>
        @empty
            <p class="text-ink-soft">{{ __('No records found.') }}</p>
        @endforelse
    </div>

    <style>
        .stock-preview-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        .stock-preview-card {
            position: relative;
            border: 1px solid rgb(197 217 203 / 0.95);
            border-radius: 14px;
            background: linear-gradient(160deg, #fff, rgb(var(--sage-rgb) / 0.35));
            padding: 0.9rem 1rem 1rem;
        }
        .stock-preview-card h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--leaf-dark); line-height: 1.25; }
        .stock-preview-card .meta { margin: 0.25rem 0 0; font-size: 0.72rem; color: var(--ink-soft); }
        .stock-preview-card .qty { margin: 0.65rem 0 0; font-family: var(--font-display), Georgia, serif; font-size: 1.65rem; font-weight: 700; color: var(--leaf-dark); }
        .stock-preview-card .qty span { font-size: 0.85rem; font-weight: 600; color: var(--ink-soft); }
        .stock-preview-card .rate { margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--ink-soft); }
        .stock-preview-card .pill { position: absolute; top: 0.55rem; right: 0.55rem; font-size: 0.65rem; font-weight: 800; color: #9a3412; background: #ffedd5; border-radius: 999px; padding: 0.15rem 0.45rem; }
        .stock-preview-card.is-low { border-color: #fdba74; background: linear-gradient(160deg, #fff7ed, #ffedd5); }
        .stock-preview-card.is-empty { opacity: 0.55; }
    </style>
</x-app-layout>
