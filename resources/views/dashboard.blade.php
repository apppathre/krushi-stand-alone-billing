<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Dashboard') }}</h1>
            <p class="text-xs text-ink-soft">
                @if (($workScope ?? 'shop') === 'shop')
                    {{ __('Whole shop') }}
                @else
                    {{ __('Place') }}: <span class="font-semibold">{{ $currentGodown->display_name ?? '-' }}</span>
                @endif
                · {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
            </p>
        </div>
    </x-slot>

    <div
        class="space-y-5"
        x-data="dashboardBoard({
            pulse: @js(route('dashboard.pulse')),
            figures: @js(route('dashboard.figures')),
            recent: @js(route('dashboard.recent')),
            calendar: @js(route('dashboard.calendar')),
        })"
    >
        <div class="dash-welcome">
            <div>
                <strong>{{ __('Welcome') }}, {{ auth()->user()->name }}</strong>
                <span>{{ __('Page opens instantly — figures fill in softly.') }}</span>
            </div>
            <button type="button" class="btn-secondary btn-sm" @click="refresh()" :disabled="pulseLoading || figuresLoading || recentLoading">
                <span x-show="!(pulseLoading || figuresLoading || recentLoading)">{{ __('Refresh') }}</span>
                <span x-show="pulseLoading || figuresLoading || recentLoading" x-cloak class="inline-flex items-center gap-2">
                    <span class="ui-spinner"></span> {{ __('Loading…') }}
                </span>
            </button>
        </div>

        @if (! empty($shortcuts))
            <div class="dash-pins" aria-label="{{ __('Shortcuts') }}">
                @foreach ($shortcuts as $pin)
                    <a class="dash-pin dash-pin--{{ $pin['tone'] }}" href="{{ route($pin['route']) }}">
                        <span class="dash-pin__ico"><x-icon :name="$pin['icon']" class="h-5 w-5" /></span>
                        <span class="dash-pin__label">{{ $pin['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <section class="card card-body">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="card-title">{{ __('Shop pulse') }}</h2>
                    <p class="text-xs text-ink-soft">{{ __('Click a count for the list. Matches TMS / ERP dashboard alerts.') }}</p>
                </div>
                <span class="dash-chip">{{ now()->format('d M Y') }}</span>
            </div>

            {{-- Skeleton while pulse loads --}}
            <div class="dash-kpi-grid" x-show="pulseLoading" :aria-busy="true">
                @foreach (range(1, 4) as $i)
                    <div class="dash-kpi dash-kpi--skel" aria-hidden="true">
                        <span class="dash-skel dash-skel--icon"></span>
                        <span class="min-w-0 flex-1 space-y-2">
                            <span class="dash-skel dash-skel--label block"></span>
                            <span class="dash-skel dash-skel--value block"></span>
                            <span class="dash-skel dash-skel--hint block"></span>
                        </span>
                    </div>
                @endforeach
            </div>

            <p class="text-sm text-accent" x-show="!pulseLoading && pulseError" x-cloak>
                {{ __('Could not load alerts.') }}
                <button type="button" class="font-semibold text-leaf underline" @click="loadPulse()">{{ __('Retry') }}</button>
            </p>

            <div class="dash-kpi-grid" x-show="!pulseLoading && pulse" x-cloak>
                <button type="button" class="dash-kpi dash-kpi--amber" x-show="pulse.show_low"
                        onclick="KrushiDrawer.open('{{ route('dashboard.alerts.low-stock') }}', '{{ __('Low Stock Alerts') }}', '30rem')">
                    <span class="dash-kpi__icon"><x-icon name="alert" class="h-5 w-5" /></span>
                    <span>
                        <span class="dash-kpi__label">{{ __('Low stock') }}</span>
                        <strong class="dash-kpi__value" :class="pulse.low_count > 0 ? 'is-hot' : ''" x-text="pulse.low_count"></strong>
                        <em class="dash-kpi__hint" x-text="pulse.low_hint"></em>
                    </span>
                </button>

                <button type="button" class="dash-kpi dash-kpi--red" x-show="pulse.show_expiry"
                        onclick="KrushiDrawer.open('{{ route('dashboard.alerts.expiry') }}', '{{ __('Batches Expiring in 60 Days') }}', '30rem')">
                    <span class="dash-kpi__icon"><x-icon name="box" class="h-5 w-5" /></span>
                    <span>
                        <span class="dash-kpi__label">{{ __('Expiry alerts') }}</span>
                        <strong class="dash-kpi__value" :class="pulse.expiry_count > 0 ? 'is-hot' : ''" x-text="pulse.expiry_count"></strong>
                        <em class="dash-kpi__hint" x-text="pulse.expiry_hint"></em>
                    </span>
                </button>

                <button type="button" class="dash-kpi dash-kpi--teal" x-show="pulse.show_recv"
                        onclick="KrushiDrawer.open('{{ route('dashboard.alerts.outstanding', ['side' => 'receivable']) }}', '{{ __('Receivables') }}', '30rem')">
                    <span class="dash-kpi__icon"><x-icon name="receipt" class="h-5 w-5" /></span>
                    <span>
                        <span class="dash-kpi__label">{{ __('Receivables') }}</span>
                        <strong class="dash-kpi__value" :class="pulse.recv_count > 0 ? 'is-hot' : ''" x-text="pulse.recv_count"></strong>
                        <em class="dash-kpi__hint">&#8377; <span x-text="pulse.receivable_fmt"></span> · {{ __('Click for details') }}</em>
                    </span>
                </button>

                <button type="button" class="dash-kpi dash-kpi--blue" x-show="pulse.show_pay"
                        onclick="KrushiDrawer.open('{{ route('dashboard.alerts.outstanding', ['side' => 'payable']) }}', '{{ __('Payable to Suppliers') }}', '30rem')">
                    <span class="dash-kpi__icon"><x-icon name="payment" class="h-5 w-5" /></span>
                    <span>
                        <span class="dash-kpi__label">{{ __('Payables') }}</span>
                        <strong class="dash-kpi__value" :class="pulse.pay_count > 0 ? 'is-hot' : ''" x-text="pulse.pay_count"></strong>
                        <em class="dash-kpi__hint">&#8377; <span x-text="pulse.payable_fmt"></span> · {{ __('Click for details') }}</em>
                    </span>
                </button>
            </div>

            <div class="mt-4" x-show="!pulseLoading && pulse && pulse.licences && pulse.licences.length" x-cloak>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-ink">{{ __('Licences') }}</h3>
                    @can('menu', ['settings_company', 'view'])
                        <a href="{{ route('settings.company') }}" class="text-xs font-semibold text-leaf hover:underline">{{ __('Manage') }}</a>
                    @endcan
                </div>
                <div class="dash-lic-grid">
                    <template x-for="lic in pulse.licences" :key="lic.label">
                        <div class="dash-lic" :class="{ 'dash-lic--warn': lic.soon, 'dash-lic--bad': lic.expired }">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-ink-soft" x-text="lic.label"></span>
                                <button type="button" class="btn-secondary btn-sm !px-2 !py-0.5 text-[0.65rem]"
                                        @click="navigator.clipboard.writeText(lic.number).then(() => $store.ui.toast(@js(__('Copied'))))"
                                        x-show="lic.number && lic.number !== '—'">{{ __('Copy') }}</button>
                            </div>
                            <p class="mt-1 font-mono text-sm font-semibold" x-text="lic.number"></p>
                            <p class="mt-0.5 text-xs text-ink-soft" x-show="lic.valid_till">
                                {{ __('Valid till') }} <span x-text="lic.valid_till"></span>
                                <span x-show="lic.expired" class="font-semibold text-accent"> · {{ __('Expired') }}</span>
                                <span x-show="lic.soon && !lic.expired" class="font-semibold text-amber-700"> · {{ __('Renew soon') }}</span>
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        @if ($liveStockOnDashboard ?? false)
            <section class="card card-body" x-data="dashLiveStock({
                url: @js(route('dashboard.live-stock')),
                refresh: {{ (int) ($liveStockRefresh ?? 30) }},
            })" x-init="load()">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="card-title" x-text="title || @js(__('Live stock'))"></h2>
                        <p class="text-xs text-ink-soft">
                            <span x-show="godown" x-text="godown + ' · '"></span>
                            {{ __('Qty updates every') }} {{ (int) ($liveStockRefresh ?? 30) }}s
                            · {{ __('Low') }}: <strong x-text="low"></strong>
                            · <span x-text="clock"></span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ url('/live-stock') }}" target="_blank" rel="noopener" class="btn-secondary btn-sm">{{ __('Open TV board') }}</a>
                        <button type="button" class="btn-secondary btn-sm" @click="load()" :disabled="loading">{{ __('Refresh') }}</button>
                    </div>
                </div>
                <p class="text-sm text-accent" x-show="error" x-cloak x-text="error"></p>
                <div class="overflow-x-auto rounded-lg border border-sage" x-show="!error">
                    <table class="w-full text-sm">
                        <thead class="bg-cream text-left text-xs uppercase tracking-wide text-ink-soft">
                            <tr>
                                <th class="px-3 py-2">{{ __('Item') }}</th>
                                <th class="px-3 py-2">{{ __('Code') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-right" x-show="showRate">{{ __('Rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="it in items.slice(0, 40)" :key="it.id">
                                <tr class="border-t border-sage" :class="{ 'bg-amber-50': it.low, 'opacity-50': it.qty <= 0 }">
                                    <td class="px-3 py-1.5 font-semibold" x-text="it.name"></td>
                                    <td class="px-3 py-1.5 text-ink-soft" x-text="it.code || '—'"></td>
                                    <td class="px-3 py-1.5 text-right font-display text-base font-bold">
                                        <span x-text="Number(it.qty).toLocaleString(undefined, { maximumFractionDigits: 3 })"></span>
                                        <span class="text-xs font-semibold text-ink-soft" x-text="it.unit || ''"></span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right" x-show="showRate" x-text="it.rate != null ? ('₹ ' + Number(it.rate).toFixed(2)) : '—'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-ink-soft" x-show="items.length > 40" x-cloak>
                    {{ __('Showing first 40 items — open the TV board for the full list.') }}
                </p>
                <script>
                    function dashLiveStock(cfg) {
                        return {
                            items: [], low: 0, title: '', godown: '', showRate: false, clock: '', error: '', loading: false,
                            load() {
                                this.loading = true;
                                fetch(cfg.url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                                    .then(r => r.json().then(d => ({ ok: r.ok, d })))
                                    .then(({ ok, d }) => {
                                        if (!ok || !d.ok) { this.error = d.message === 'off' ? @js(__('Live stock panel is off.')) : @js(__('Could not load live stock.')); return; }
                                        this.error = '';
                                        this.items = d.items || [];
                                        this.low = d.low || 0;
                                        this.title = d.title || '';
                                        this.godown = d.godown || '';
                                        this.showRate = !!d.show_rate;
                                        this.clock = d.at || '';
                                    })
                                    .catch(() => { this.error = @js(__('Could not load live stock.')); })
                                    .finally(() => { this.loading = false; });
                            },
                            init() {
                                setInterval(() => this.load(), cfg.refresh * 1000);
                            }
                        };
                    }
                </script>
            </section>
        @endif

        <section class="card card-body">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="card-title">{{ __('Due calendar') }}</h2>
                    <p class="text-xs text-ink-soft">{{ __('Bill due dates and licence renewals — filtered by your role.') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="shiftMonth(-1)">‹</button>
                    <span class="dash-chip min-w-[9rem] text-center" x-text="calLabel()"></span>
                    <button type="button" class="btn-secondary btn-sm" @click="shiftMonth(1)">›</button>
                </div>
            </div>

            <div x-show="calendarLoading" class="dash-skel dash-skel--row mb-3" aria-hidden="true"></div>
            <p class="text-sm text-accent" x-show="!calendarLoading && calendarError" x-cloak>
                {{ __('Could not load calendar.') }}
                <button type="button" class="font-semibold text-leaf underline" @click="loadCalendar()">{{ __('Retry') }}</button>
            </p>

            <div class="dash-cal" x-show="!calendarLoading && calendar" x-cloak>
                <div class="dash-cal__dow">
                    <span>{{ __('Sun') }}</span><span>{{ __('Mon') }}</span><span>{{ __('Tue') }}</span>
                    <span>{{ __('Wed') }}</span><span>{{ __('Thu') }}</span><span>{{ __('Fri') }}</span><span>{{ __('Sat') }}</span>
                </div>
                <div class="dash-cal__grid">
                    <template x-for="(cell, idx) in calendarDays()" :key="idx">
                        <div class="dash-cal__cell" :class="{ 'is-empty': cell.empty, 'is-today': cell.today, 'has-recv': cell.hasRecv, 'has-pay': cell.hasPay, 'has-lic': cell.hasLic }">
                            <template x-if="!cell.empty">
                                <div>
                                    <span class="dash-cal__day" x-text="cell.day"></span>
                                    <ul class="dash-cal__list">
                                        <template x-for="(ev, ei) in (cell.events || []).slice(0, 3)" :key="ei">
                                            <li>
                                                <a :href="ev.url" class="dash-cal__ev" :class="'dash-cal__ev--' + ev.kind" :title="ev.title + ' · ' + (ev.amount_fmt || '')">
                                                    <span x-text="ev.title"></span>
                                                </a>
                                            </li>
                                        </template>
                                    </ul>
                                    <span class="dash-cal__more" x-show="(cell.events || []).length > 3" x-text="'+' + ((cell.events || []).length - 3)"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="mt-3 flex flex-wrap gap-3 text-xs text-ink-soft">
                    <span><i class="dash-dot dash-dot--recv"></i> {{ __('Receivable due') }}</span>
                    <span><i class="dash-dot dash-dot--pay"></i> {{ __('Payable due') }}</span>
                    <span><i class="dash-dot dash-dot--lic"></i> {{ __('Licence') }}</span>
                </div>
            </div>
        </section>

        {{-- Figures skeleton --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- Today's sales --}}
            <div class="stat-card">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-leaf/10 text-leaf">
                    <x-icon name="sales" class="h-5 w-5" />
                </div>
                <p class="stat-label">{{ __("Today's Sales") }}</p>
                <template x-if="figuresLoading"><div class="dash-skel dash-skel--value mt-1" aria-hidden="true"></div></template>
                <p class="stat-value" x-show="!figuresLoading && figures" x-cloak>&#8377; <span x-text="figures.today_sales_fmt"></span></p>
                <p class="relative mt-1 text-xs text-ink-soft" x-show="!figuresLoading && figures" x-cloak x-text="figures.today_count_label"></p>
                <div class="dash-skel dash-skel--hint mt-2" x-show="figuresLoading" aria-hidden="true"></div>
            </div>
            {{-- Month sales --}}
            <div class="stat-card">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-leaf/10 text-leaf">
                    <x-icon name="chart" class="h-5 w-5" />
                </div>
                <p class="stat-label">{{ __('This Month Sales') }}</p>
                <template x-if="figuresLoading"><div class="dash-skel dash-skel--value mt-1" aria-hidden="true"></div></template>
                <p class="stat-value" x-show="!figuresLoading && figures" x-cloak>&#8377; <span x-text="figures.month_sales_fmt"></span></p>
                <p class="relative mt-1 text-xs text-ink-soft" x-show="!figuresLoading && figures" x-cloak>{{ __('Purchases') }}: &#8377; <span x-text="figures.month_purchases_fmt"></span></p>
                <div class="dash-skel dash-skel--hint mt-2" x-show="figuresLoading" aria-hidden="true"></div>
            </div>
            {{-- Stock value --}}
            <div class="stat-card">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-accent/10 text-accent">
                    <x-icon name="stock" class="h-5 w-5" />
                </div>
                <p class="stat-label">{{ __('Stock Value (cost)') }}</p>
                <template x-if="figuresLoading"><div class="dash-skel dash-skel--value mt-1" aria-hidden="true"></div></template>
                <p class="stat-value" x-show="!figuresLoading && figures" x-cloak>&#8377; <span x-text="figures.stock_value_fmt"></span></p>
                <p class="relative mt-1 text-xs text-ink-soft" x-show="!figuresLoading && figures" x-cloak x-text="figures.stock_scope_label"></p>
                <div class="dash-skel dash-skel--hint mt-2" x-show="figuresLoading" aria-hidden="true"></div>
            </div>
            {{-- Open balances from pulse (same outstanding query — not duplicated) --}}
            <div class="stat-card">
                <div class="mb-3 flex h-9 w-9 items-center justify-center rounded-xl bg-leaf/10 text-leaf">
                    <x-icon name="outstanding" class="h-5 w-5" />
                </div>
                <p class="stat-label">{{ __('Open balances') }}</p>
                <template x-if="pulseLoading"><div class="dash-skel dash-skel--value mt-1" aria-hidden="true"></div></template>
                <p class="stat-value text-accent" x-show="!pulseLoading && pulse" x-cloak>&#8377; <span x-text="pulse.open_balance_fmt"></span></p>
                <p class="relative mt-1 text-xs text-ink-soft" x-show="!pulseLoading && pulse" x-cloak>{{ __('Receivable + payable') }}</p>
                <div class="dash-skel dash-skel--hint mt-2" x-show="pulseLoading" aria-hidden="true"></div>
            </div>
        </div>

        <p class="text-sm text-accent" x-show="figuresError" x-cloak>
            {{ __('Could not load figures.') }}
            <button type="button" class="font-semibold text-leaf underline" @click="loadFigures()">{{ __('Retry') }}</button>
        </p>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sage bg-sage/40 px-5 py-3">
                <h2 class="card-title">{{ __('Recent Invoices') }}</h2>
                @can('menu', ['sales', 'add'])
                    <a href="{{ route('sales.index') }}" class="btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> {{ __('New Invoice') }}</a>
                @endcan
            </div>

            <div x-show="recentLoading" class="space-y-3 px-5 py-5">
                @foreach (range(1, 4) as $i)
                    <div class="dash-skel dash-skel--row" aria-hidden="true"></div>
                @endforeach
            </div>

            <div x-show="!recentLoading && recentError" x-cloak class="px-5 py-6 text-sm text-accent">
                {{ __('Could not load recent invoices.') }}
                <button type="button" class="font-semibold text-leaf underline" @click="loadRecent()">{{ __('Retry') }}</button>
            </div>

            <div x-show="!recentLoading && !recentError" x-html="recentHtml"></div>
        </div>
    </div>
</x-app-layout>
