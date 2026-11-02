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
        .live-board { min-height: 100vh; padding: 1rem 1.25rem 2rem; transition: background .2s, color .2s; }
        .live-board.theme-day { background: #f3efe6; color: #1a2e24; }
        .live-board.theme-night { background: #0f2219; color: #f4faf6; }
        .live-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem; }
        .live-head h1 { margin: 0; font-family: var(--font-display), Georgia, serif; font-size: clamp(1.4rem, 3vw, 2rem); }
        .live-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .live-toolbar input, .live-toolbar select, .live-btn {
            border-radius: 10px; padding: .45rem .7rem; font-size: .8rem; font-weight: 700;
            border: 1px solid transparent; cursor: pointer;
        }
        .theme-day .live-toolbar input, .theme-day .live-toolbar select, .theme-day .live-btn {
            background: #fff; border-color: #d5cdb8; color: #1a2e24;
        }
        .theme-night .live-toolbar input, .theme-night .live-toolbar select, .theme-night .live-btn {
            background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); color: #fff;
        }
        .theme-night .live-toolbar option { color: #111; }
        .live-btn.is-on { outline: 2px solid #2f6b4f; outline-offset: 1px; }
        .theme-night .live-btn.is-on { outline-color: #7dcea0; }

        .live-grid { display: grid; gap: 0.65rem; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        .live-card { border-radius: 14px; padding: .85rem 1rem; }
        .theme-day .live-card { border: 1px solid #d9d0bc; background: #fff; }
        .theme-night .live-card { border: 1px solid rgba(255,255,255,.12); background: linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); }
        .live-card.is-low { border-color: rgba(251,146,60,.7) !important; background: linear-gradient(160deg, rgba(251,146,60,.22), transparent) !important; }
        .live-card.is-empty { opacity: .45; }
        .live-card h3 { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.25; }
        .live-card .meta { margin: .2rem 0 0; font-size: .72rem; opacity: .65; }
        .live-card .qty { margin: .55rem 0 0; font-family: var(--font-display), Georgia, serif; font-size: 1.8rem; font-weight: 700; }
        .live-card .qty span { font-size: .9rem; font-weight: 600; opacity: .75; }
        .live-card .rate { margin: .15rem 0 0; font-size: .85rem; opacity: .7; }

        .live-table-wrap {
            width: 100%; max-height: calc(100vh - 8.5rem); overflow: auto;
            border-radius: 14px; overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
        .theme-day .live-table-wrap { border: 1px solid #d9d0bc; background: #fff; }
        .theme-night .live-table-wrap { border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04); }
        .live-table { width: 100%; border-collapse: collapse; font-size: clamp(.9rem, 1.4vw, 1.1rem); }
        .live-table th, .live-table td { padding: .65rem .85rem; text-align: left; white-space: nowrap; }
        .live-table th { position: sticky; top: 0; z-index: 1; font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; }
        .theme-day .live-table th { background: #ebe4d6; color: #4a5c52; }
        .theme-night .live-table th { background: #163026; color: rgba(244,250,246,.7); }
        .theme-day .live-table tr { border-top: 1px solid #ebe4d6; }
        .theme-night .live-table tr { border-top: 1px solid rgba(255,255,255,.08); }
        .live-table td.num, .live-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .live-table .qty-cell { font-family: var(--font-display), Georgia, serif; font-size: 1.25rem; font-weight: 700; }
        .live-table tr.is-low { background: rgba(251,146,60,.18); }
        .live-table tr.is-empty { opacity: .5; }
        .muted { opacity: .65; font-size: .85em; }
    </style>
</head>
<body x-data="liveStockBoard({
    url: @js(route('live-stock.data')),
    refresh: {{ (int) $refresh }},
    showRate: @js($payload['show_rate']),
    items: @js($payload['items']),
    low: {{ (int) $payload['low_count'] }},
    clock: @js(now()->format('H:i:s')),
    defaultView: @js($defaultView),
    defaultTheme: @js($defaultTheme),
})" :class="'live-board antialiased theme-' + theme">
    <header class="live-head">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] muted">{{ $company->display_name }}@if ($payload['godown']) · {{ $payload['godown'] }}@endif</p>
            <h1>{{ $payload['title'] }}</h1>
            <p class="mt-1 text-sm muted">
                <span x-text="clock"></span>
                · {{ __('Low') }}: <strong x-text="low"></strong>
                · {{ __('Qty updates every') }} {{ $refresh }}s
            </p>
        </div>
        <div class="live-toolbar">
            <input type="search" x-model.debounce.400ms="q" @input="reload()" placeholder="{{ __('Search…') }}" class="w-40 sm:w-52">
            <select x-model="categoryId" @change="reload()">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->display_name }}</option>
                @endforeach
            </select>
            <button type="button" class="live-btn" :class="{ 'is-on': view === 'table' }" @click="setView('table')">{{ __('Table') }}</button>
            <button type="button" class="live-btn" :class="{ 'is-on': view === 'cards' }" @click="setView('cards')">{{ __('Cards') }}</button>
            <button type="button" class="live-btn" :class="{ 'is-on': theme === 'day' }" @click="setTheme('day')">{{ __('Day') }}</button>
            <button type="button" class="live-btn" :class="{ 'is-on': theme === 'night' }" @click="setTheme('night')">{{ __('Night') }}</button>
            <form method="POST" action="{{ route('live-stock.lock') }}">@csrf<button class="live-btn">{{ __('Lock') }}</button></form>
        </div>
    </header>

    {{-- Full table (default): mouse-wheel / touch scroll --}}
    <div class="live-table-wrap" x-show="view === 'table'" x-cloak>
        <table class="live-table">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="num">{{ __('Qty') }}</th>
                    <th x-show="showRate" class="num">{{ __('Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="it in items" :key="'t'+it.id">
                    <tr :class="{ 'is-low': it.low, 'is-empty': it.qty <= 0 }">
                        <td x-text="it.name"></td>
                        <td class="muted" x-text="it.code || '—'"></td>
                        <td class="muted" x-text="it.category || '—'"></td>
                        <td class="num qty-cell">
                            <span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span>
                            <span class="muted" x-text="it.unit || ''"></span>
                        </td>
                        <td class="num" x-show="showRate" x-text="it.rate != null ? ('₹ ' + Number(it.rate).toFixed(2)) : '—'"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="live-grid" x-show="view === 'cards'" x-cloak>
        <template x-for="it in items" :key="'c'+it.id">
            <article class="live-card" :class="{ 'is-low': it.low, 'is-empty': it.qty <= 0 }">
                <h3 x-text="it.name"></h3>
                <p class="meta"><span x-text="it.code"></span><span x-show="it.category" x-text="' · ' + it.category"></span></p>
                <p class="qty"><span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span> <span x-text="it.unit || ''"></span></p>
                <p class="rate" x-show="showRate && it.rate != null" x-text="'₹ ' + Number(it.rate).toFixed(2)"></p>
            </article>
        </template>
    </div>

    <p class="mt-6 text-center text-sm muted" x-show="items.length === 0" x-cloak>{{ __('No items marked for the live board.') }}</p>

    <script>
        function liveStockBoard(cfg) {
            const storeKey = 'krushi.liveStock.prefs';
            let saved = {};
            try { saved = JSON.parse(localStorage.getItem(storeKey) || '{}') || {}; } catch (e) { saved = {}; }
            return {
                items: cfg.items,
                low: cfg.low,
                showRate: cfg.showRate,
                clock: cfg.clock,
                q: '',
                categoryId: '',
                view: saved.view === 'cards' || saved.view === 'table' ? saved.view : cfg.defaultView,
                theme: saved.theme === 'night' || saved.theme === 'day' ? saved.theme : cfg.defaultTheme,
                timer: null,
                init() {
                    this.timer = setInterval(() => this.reload(true), cfg.refresh * 1000);
                    setInterval(() => {
                        const d = new Date();
                        this.clock = d.toTimeString().slice(0, 8);
                    }, 1000);
                },
                persist() {
                    try { localStorage.setItem(storeKey, JSON.stringify({ view: this.view, theme: this.theme })); } catch (e) {}
                },
                setView(v) { this.view = v; this.persist(); },
                setTheme(t) { this.theme = t; this.persist(); },
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
            };
        }
    </script>
</body>
</html>
