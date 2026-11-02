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
        :root {
            --ls-bg: #ffffff;
            --ls-fg: #111111;
            --ls-muted: #555555;
            --ls-line: #111111;
            --ls-soft: #e8e8e8;
            --ls-head: #111111;
            --ls-head-fg: #ffffff;
            --ls-zebra: #f5f5f5;
            --ls-low: #111111;
            --ls-low-bg: #efefef;
            --ls-btn: #ffffff;
            --ls-btn-border: #111111;
        }
        .live-board.theme-night {
            --ls-bg: #0a0a0a;
            --ls-fg: #f5f5f5;
            --ls-muted: #a3a3a3;
            --ls-line: #f5f5f5;
            --ls-soft: #2a2a2a;
            --ls-head: #f5f5f5;
            --ls-head-fg: #0a0a0a;
            --ls-zebra: #161616;
            --ls-low: #f5f5f5;
            --ls-low-bg: #262626;
            --ls-btn: #0a0a0a;
            --ls-btn-border: #f5f5f5;
        }

        html, body { height: 100%; }
        .live-board {
            min-height: 100vh;
            margin: 0;
            background: var(--ls-bg);
            color: var(--ls-fg);
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            display: flex;
            flex-direction: column;
        }
        .live-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 0.85rem 1rem 1rem;
            box-sizing: border-box;
        }

        .live-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.75rem 1rem;
            padding-bottom: 0.7rem;
            border-bottom: 2px solid var(--ls-line);
            margin-bottom: 0.75rem;
        }
        .live-kicker {
            margin: 0;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--ls-muted);
        }
        .live-head h1 {
            margin: 0.15rem 0 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            font-size: clamp(1.35rem, 2.6vw, 1.85rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }
        .live-meta {
            margin: 0.35rem 0 0;
            font-size: 0.82rem;
            color: var(--ls-muted);
            font-variant-numeric: tabular-nums;
        }
        .live-meta strong { color: var(--ls-fg); font-weight: 700; }

        .live-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
        .live-toolbar input,
        .live-toolbar select,
        .live-btn {
            height: 2.15rem;
            border: 1.5px solid var(--ls-btn-border);
            background: var(--ls-btn);
            color: var(--ls-fg);
            border-radius: 0;
            padding: 0 0.7rem;
            font-size: 0.78rem;
            font-weight: 650;
            letter-spacing: 0.02em;
        }
        .live-toolbar input:focus,
        .live-toolbar select:focus,
        .live-btn:focus { outline: 2px solid var(--ls-fg); outline-offset: 1px; }
        .live-btn { cursor: pointer; }
        .live-btn.is-on { background: var(--ls-fg); color: var(--ls-bg); }
        .live-toolbar option { color: #111; background: #fff; }

        .live-table-wrap {
            flex: 1;
            min-height: 0;
            overflow: auto;
            border: 2px solid var(--ls-line);
            background: var(--ls-bg);
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
        .live-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: clamp(0.95rem, 1.35vw, 1.2rem);
        }
        .live-table col.c-item { width: 42%; }
        .live-table col.c-code { width: 14%; }
        .live-table col.c-cat { width: 18%; }
        .live-table col.c-qty { width: 14%; }
        .live-table col.c-rate { width: 12%; }

        .live-table th,
        .live-table td {
            padding: 0.72rem 0.9rem;
            text-align: left;
            border-bottom: 1px solid var(--ls-soft);
            vertical-align: middle;
        }
        .live-table th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--ls-head);
            color: var(--ls-head-fg);
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-bottom: 2px solid var(--ls-line);
        }
        .live-table tbody tr:nth-child(even) { background: var(--ls-zebra); }
        .live-table tbody tr:hover { background: var(--ls-soft); }
        .live-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .live-table .item-name {
            font-weight: 650;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .live-table .muted {
            color: var(--ls-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .live-table .qty-cell {
            font-weight: 800;
            font-size: 1.12em;
            letter-spacing: -0.01em;
            white-space: nowrap;
        }
        .live-table .qty-unit {
            margin-left: 0.25rem;
            font-size: 0.72em;
            font-weight: 600;
            color: var(--ls-muted);
        }
        .live-table tr.is-low td {
            background: var(--ls-low-bg);
            box-shadow: inset 4px 0 0 var(--ls-low);
            font-weight: 700;
        }
        .live-table tr.is-empty { opacity: 0.48; }
        .live-table tr.is-empty .qty-cell { text-decoration: line-through; }

        .live-grid {
            flex: 1;
            display: grid;
            gap: 0.65rem;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            align-content: start;
            overflow: auto;
        }
        .live-card {
            border: 2px solid var(--ls-line);
            background: var(--ls-bg);
            padding: 0.85rem 0.95rem;
        }
        .live-card.is-low { box-shadow: inset 4px 0 0 var(--ls-line); background: var(--ls-zebra); }
        .live-card.is-empty { opacity: 0.48; }
        .live-card h3 { margin: 0; font-size: 1.05rem; font-weight: 700; line-height: 1.25; }
        .live-card .meta { margin: 0.25rem 0 0; font-size: 0.75rem; color: var(--ls-muted); }
        .live-card .qty { margin: 0.65rem 0 0; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; }
        .live-card .qty span { font-size: 0.85rem; font-weight: 600; color: var(--ls-muted); }
        .live-card .rate { margin: 0.2rem 0 0; font-size: 0.85rem; color: var(--ls-muted); }

        .live-empty {
            margin: 2.5rem auto;
            text-align: center;
            color: var(--ls-muted);
            font-size: 0.95rem;
        }
        .live-foot {
            margin-top: 0.55rem;
            font-size: 0.7rem;
            color: var(--ls-muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
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
})" class="live-board antialiased" :class="'theme-' + theme">
    <div class="live-shell">
        <header class="live-head">
            <div>
                <p class="live-kicker">{{ $company->display_name }}@if ($payload['godown']) · {{ $payload['godown'] }}@endif</p>
                <h1>{{ $payload['title'] }}</h1>
                <p class="live-meta">
                    <span x-text="clock"></span>
                    · {{ __('Items') }}: <strong x-text="items.length"></strong>
                    · {{ __('Low stock') }}: <strong x-text="low"></strong>
                    · {{ __('Auto') }} {{ $refresh }}s
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
                <button type="button" class="live-btn" :class="{ 'is-on': theme === 'day' }" @click="setTheme('day')">{{ __('White') }}</button>
                <button type="button" class="live-btn" :class="{ 'is-on': theme === 'night' }" @click="setTheme('night')">{{ __('Black') }}</button>
                <form method="POST" action="{{ route('live-stock.lock') }}">@csrf<button class="live-btn">{{ __('Lock') }}</button></form>
            </div>
        </header>

        <div class="live-table-wrap" x-show="view === 'table'" x-cloak>
            <table class="live-table">
                <colgroup>
                    <col class="c-item">
                    <col class="c-code">
                    <col class="c-cat">
                    <col class="c-qty">
                    <col class="c-rate" x-show="showRate">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th class="num">{{ __('Qty') }}</th>
                        <th class="num" x-show="showRate">{{ __('Rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="it in items" :key="'t'+it.id">
                        <tr :class="{ 'is-low': it.low, 'is-empty': it.qty <= 0 }">
                            <td class="item-name" x-text="it.name" :title="it.name"></td>
                            <td class="muted" x-text="it.code || '—'"></td>
                            <td class="muted" x-text="it.category || '—'"></td>
                            <td class="num qty-cell">
                                <span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span>
                                <span class="qty-unit" x-text="it.unit || ''"></span>
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
                    <p class="meta"><span x-text="it.code || '—'"></span><span x-show="it.category" x-text="' · ' + it.category"></span></p>
                    <p class="qty">
                        <span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span>
                        <span x-text="it.unit || ''"></span>
                    </p>
                    <p class="rate" x-show="showRate && it.rate != null" x-text="'₹ ' + Number(it.rate).toFixed(2)"></p>
                </article>
            </template>
        </div>

        <p class="live-empty" x-show="items.length === 0" x-cloak>{{ __('No items marked for the live board.') }}</p>

        <div class="live-foot">
            <span>{{ __('Scroll with mouse wheel · qty refreshes automatically') }}</span>
            <span x-text="(theme === 'night' ? @js(__('Black board')) : @js(__('White board'))) + ' · ' + (view === 'cards' ? @js(__('Cards')) : @js(__('Table')))"></span>
        </div>
    </div>

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
