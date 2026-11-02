<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $payload['title'] }}</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background: #0f2219; color: #f4faf6; }
        .live-board { min-height: 100vh; padding: 1rem 1.25rem 2rem; }
        .live-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; }
        .live-head h1 { margin: 0; font-family: var(--font-display), Georgia, serif; font-size: clamp(1.4rem, 3vw, 2rem); }
        .live-grid { display: grid; gap: 0.65rem; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        .live-card { border-radius: 14px; border: 1px solid rgba(255,255,255,.12); background: linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); padding: .85rem 1rem; }
        .live-card.is-low { border-color: rgba(251,146,60,.7); background: linear-gradient(160deg, rgba(251,146,60,.25), rgba(255,255,255,.04)); }
        .live-card.is-empty { opacity: .45; }
        .live-card h3 { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.25; }
        .live-card .meta { margin: .2rem 0 0; font-size: .72rem; color: rgba(244,250,246,.65); }
        .live-card .qty { margin: .55rem 0 0; font-family: var(--font-display), Georgia, serif; font-size: 1.8rem; font-weight: 700; }
        .live-card .qty span { font-size: .9rem; font-weight: 600; opacity: .75; }
        .live-card .rate { margin: .15rem 0 0; font-size: .85rem; color: rgba(244,250,246,.7); }
        .live-toolbar input, .live-toolbar select { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.18); color: #fff; border-radius: 10px; padding: .45rem .65rem; }
        .live-toolbar option { color: #111; }
    </style>
</head>
<body x-data="liveStockBoard({
    url: @js(route('live-stock.data')),
    refresh: {{ (int) $refresh }},
    showRate: @js($payload['show_rate']),
    items: @js($payload['items']),
    low: {{ (int) $payload['low_count'] }},
    clock: @js(now()->format('H:i:s')),
})" class="live-board antialiased">
    <header class="live-head">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-white/50">{{ $company->display_name }}@if ($payload['godown']) · {{ $payload['godown'] }}@endif</p>
            <h1>{{ $payload['title'] }}</h1>
            <p class="mt-1 text-sm text-white/60">
                <span x-text="clock"></span>
                · {{ __('Low') }}: <strong x-text="low"></strong>
                · {{ __('Auto refresh') }} {{ $refresh }}s
            </p>
        </div>
        <div class="live-toolbar flex flex-wrap items-center gap-2">
            <input type="search" x-model.debounce.400ms="q" @input="reload()" placeholder="{{ __('Search…') }}" class="w-40 sm:w-52">
            <select x-model="categoryId" @change="reload()">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->display_name }}</option>
                @endforeach
            </select>
            <form method="POST" action="{{ route('live-stock.lock') }}">@csrf<button class="rounded-lg border border-white/25 px-3 py-1.5 text-xs font-bold text-white/80">{{ __('Lock') }}</button></form>
        </div>
    </header>

    <div class="live-grid">
        <template x-for="it in items" :key="it.id">
            <article class="live-card" :class="{ 'is-low': it.low, 'is-empty': it.qty <= 0 }">
                <h3 x-text="it.name"></h3>
                <p class="meta"><span x-text="it.code"></span><span x-show="it.category" x-text="' · ' + it.category"></span></p>
                <p class="qty"><span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span> <span x-text="it.unit || ''"></span></p>
                <p class="rate" x-show="showRate && it.rate != null" x-text="'₹ ' + Number(it.rate).toFixed(2)"></p>
            </article>
        </template>
    </div>
    <p class="mt-6 text-center text-sm text-white/40" x-show="items.length === 0" x-cloak>{{ __('No items to show.') }}</p>

    <script>
        function liveStockBoard(cfg) {
            return {
                items: cfg.items,
                low: cfg.low,
                showRate: cfg.showRate,
                clock: cfg.clock,
                q: '',
                categoryId: '',
                timer: null,
                init() {
                    this.tick();
                    this.timer = setInterval(() => { this.tick(); this.reload(true); }, cfg.refresh * 1000);
                    setInterval(() => {
                        const d = new Date();
                        this.clock = d.toTimeString().slice(0, 8);
                    }, 1000);
                },
                async reload(silent = false) {
                    const u = new URL(cfg.url, window.location.origin);
                    if (this.q) u.searchParams.set('q', this.q);
                    if (this.categoryId) u.searchParams.set('category_id', this.categoryId);
                    try {
                        const res = await fetch(u.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                        if (res.status === 403) { window.location = @js(route('live-stock.gate')); return; }
                        const data = await res.json();
                        if (data.ok) {
                            this.items = data.items;
                            this.low = data.low;
                            if (data.at) this.clock = data.at;
                        }
                    } catch (e) {
                        if (!silent) console.warn(e);
                    }
                },
                tick() {},
            };
        }
    </script>
</body>
</html>
