<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Owner / App control') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Default values for parties, commission and invoice print. Shop name & GSTIN stay under Shop registration.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('settings.app-control.update') }}" class="max-w-4xl space-y-5">
        @csrf @method('PUT')

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Party credit defaults') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Applied when adding a new customer or supplier. Staff can still change per party.') }}</p>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2">
                <x-field name="default_credit_days" type="number" min="0" max="365" :label="__('Default credit days')"
                         :value="old('default_credit_days', $company->default_credit_days ?? 30)" required
                         :hint="__('Usually 30 — used for ageing due dates')" />
                <x-field name="overdue_interest_pct" type="number" step="0.01" min="0" max="100" :label="__('Overdue interest % p.a.')"
                         :value="old('overdue_interest_pct', $company->overdue_interest_pct ?? 0)"
                         :hint="__('0 = off. Shown on bill-wise ageing; Apply posts a journal.')" />
                <x-field name="default_credit_limit" type="number" step="0.01" min="0" :label="__('Default credit limit (₹)')"
                         :value="old('default_credit_limit', $company->default_credit_limit ?? 0)" required
                         :hint="__('0 = no limit suggested')" />
                <x-check name="enforce_credit_limit" :label="__('Block sale when customer exceeds credit limit')"
                         :checked="old('enforce_credit_limit', $company->enforce_credit_limit ?? false)" class="md:col-span-2" />
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Opening balance Dr / Cr defaults') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Dr = debit (asset / they owe you). Cr = credit (liability / you owe them).') }}</p>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2">
                <x-select-field name="default_client_opening_type" :label="__('New customer opening side')"
                    :value="old('default_client_opening_type', $company->default_client_opening_type ?: 'dr')"
                    :options="['dr' => __('Debit (Dr) — customer owes shop'), 'cr' => __('Credit (Cr) — advance from customer')]" required />
                <x-select-field name="default_supplier_opening_type" :label="__('New supplier opening side')"
                    :value="old('default_supplier_opening_type', $company->default_supplier_opening_type ?: 'cr')"
                    :options="['cr' => __('Credit (Cr) — shop owes supplier'), 'dr' => __('Debit (Dr) — advance to supplier')]" required />
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Commission') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('When enabled, sales post commission from party salesperson % (or default % below).') }}</p>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2">
                <x-check name="commission_enabled" :label="__('Enable salesperson commission on sales')"
                         :checked="old('commission_enabled', $company->commission_enabled ?? false)" class="md:col-span-2" />
                <x-field name="default_commission_pct" type="number" step="0.01" min="0" max="100"
                         :label="__('Default commission %')"
                         :value="old('default_commission_pct', $company->default_commission_pct ?? 0)"
                         :hint="__('Used when party has salesperson but no own %')" />
                <x-select-field name="commission_expense_ledger_id" :label="__('Commission expense ledger')"
                    :value="old('commission_expense_ledger_id', $map->commission_expense_ledger_id)"
                    :placeholder="__('— not set —')"
                    :options="$accounts->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all()" />
                <x-select-field name="commission_payable_ledger_id" :label="__('Commission payable ledger')"
                    :value="old('commission_payable_ledger_id', $map->commission_payable_ledger_id)"
                    :placeholder="__('— not set —')"
                    :options="$accounts->mapWithKeys(fn ($l) => [$l->id => $l->display_name])->all()" />
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Invoice print defaults') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Pre-selected on the Print menu. Staff can still pick another format or language.') }}</p>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-3">
                <x-select-field name="default_invoice_print_format" :label="__('Default format')"
                    :value="old('default_invoice_print_format', $company->default_invoice_print_format ?: 'standard')"
                    :options="$printFormats" required />
                <x-select-field name="invoice_print_lang" :label="__('Default language')"
                    :value="old('invoice_print_lang', $company->invoice_print_lang ?: 'auto')"
                    :options="['auto' => __('Follow app language'), 'en' => __('English'), 'mr' => __('Marathi')]" required />
                <x-select-field name="default_print_size" :label="__('Default paper size')"
                    :value="old('default_print_size', $company->default_print_size ?: 'a4')"
                    :options="['a4' => __('A4'), 'half' => __('Half (A5)'), 'quarter' => __('1/4 page')]" required />
            </div>
        </section>

        <section class="card" id="live-stock">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Live stock screen (shop TV)') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Public board at /live-stock — unlock with a passkey, no staff login. Turn off anytime.') }}</p>
            </div>
            <div class="card-body grid gap-4 md:grid-cols-2">
                <x-check name="live_stock_enabled" :label="__('Enable live stock screen')"
                         :checked="old('live_stock_enabled', $company->live_stock_enabled ?? false)" class="md:col-span-2" />
                <x-field name="live_stock_title" :label="__('Screen title')"
                         :value="old('live_stock_title', $company->live_stock_title)" :placeholder="__('Live stock')" />
                <x-field name="live_stock_passkey" type="password" :label="__('Passkey (leave blank to keep)')"
                         :value="''" :hint="$company->live_stock_passkey ? __('Passkey is set. Enter a new one to change.') : __('Set a passkey before enabling.')" autocomplete="new-password" />
                <x-field name="live_stock_refresh_sec" type="number" min="10" max="300" :label="__('Refresh seconds')"
                         :value="old('live_stock_refresh_sec', $company->live_stock_refresh_sec ?? 30)" />
                <x-select-field name="live_stock_godown_id" :label="__('Godown on board')"
                    :value="old('live_stock_godown_id', $company->live_stock_godown_id)"
                    :placeholder="__('All places')"
                    :options="\App\Models\Godown::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($g) => [$g->id => $g->display_name])->all()" />
                <x-check name="live_stock_show_rate" :label="__('Show sale rate on board')"
                         :checked="old('live_stock_show_rate', $company->live_stock_show_rate ?? false)" />
                <x-check name="live_stock_on_dashboard" :label="__('Show live stock panel on staff dashboard')"
                         :checked="old('live_stock_on_dashboard', $company->live_stock_on_dashboard ?? false)" />
                <x-select-field name="live_stock_default_view" :label="__('Default board layout')"
                    :value="old('live_stock_default_view', $company->live_stock_default_view ?? 'table')"
                    :options="['table' => __('Full table (scroll)'), 'cards' => __('Cards / tiles')]" />
                <x-select-field name="live_stock_theme" :label="__('Default board colour')"
                    :value="old('live_stock_theme', $company->live_stock_theme ?? 'day')"
                    :options="['day' => __('White board (professional)'), 'night' => __('Black board')]"
                    :hint="__('TV screen buttons can still switch Table/Cards and White/Black.')" />
                <p class="text-xs text-ink-soft md:col-span-2">
                    {{ __('Only items with “Show on live stock board” in Item Master appear. Qty refreshes automatically.') }}
                </p>
                <p class="text-xs text-ink-soft md:col-span-2">
                    {{ __('Open') }}: <a class="font-semibold text-leaf hover:underline" href="{{ url('/live-stock') }}" target="_blank" rel="noopener">{{ url('/live-stock') }}</a>
                    · <a class="font-semibold text-leaf hover:underline" href="{{ route('stock.preview') }}">{{ __('Staff stock preview') }}</a>
                </p>
            </div>
        </section>

        @can('menu', ['settings_app_control', 'edit'])
            <div><button class="btn-primary">{{ __('Save app control') }}</button></div>
        @endcan
    </form>
</x-app-layout>
