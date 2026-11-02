<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('Shop registration') }}</h1>
            <p class="text-sm font-normal text-ink-soft">
                {{ __('Company profile') }} — {{ $company->name_mr && app()->getLocale() === 'mr' ? $company->name_mr : $company->name }}
            </p>
        </div>
    </x-slot>

    <p class="mb-4 max-w-4xl text-sm text-ink-soft">
        {{ __('Register your shop once. These details appear on every invoice, receipt and report. Update any section below and save — no separate page for each field.') }}
    </p>

    <form method="POST" action="{{ route('settings.company.update') }}" class="max-w-4xl space-y-5">
        @csrf @method('PUT')

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Business details') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Shop name, address and contact shown on invoices.') }}</p>
            </div>
            <div class="card-body">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-field name="name" :label="__('Name (English)')" :value="$company->name" required
                             :hint="__('Marathi name updates as you type (edit Marathi anytime).')" />
                    <x-field name="name_mr" :label="__('Name (Marathi)')" :value="$company->name_mr"
                             :hint="__('Auto from English name. Clear to resume auto-fill.')" />
                    <x-field name="short_code" :label="__('Short code')" :value="$company->short_code"
                             :hint="__('Shop short code for series / prints (e.g. SPK)')" />
                    <x-field name="tagline" :label="__('Tagline')" :value="$company->tagline" class="md:col-span-2" />
                    <x-field name="address" :label="__('Address')" :value="$company->address" class="md:col-span-2" />
                    <x-field name="city" :label="__('City')" :value="$company->city" />
                    <x-field name="pincode" :label="__('Pincode')" :value="$company->pincode" />
                    <x-select-field name="state_code" :label="__('State (determines CGST/SGST vs IGST)')" :value="$company->state_code" :options="$states" required />
                    <x-field name="phone" :label="__('Phone')" :value="$company->phone" />
                    <x-field name="email" type="email" :label="__('Email')" :value="$company->email" />
                </div>
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Tax & licences') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('GSTIN, PAN, Shop Act and agro licences — copy numbers for inspectors or renewals.') }}</p>
            </div>
            <div class="card-body space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-field name="gstin" :label="__('GSTIN')" :value="$company->gstin" maxlength="15" />
                    <x-field name="pan" :label="__('PAN')" :value="$company->pan" maxlength="10" />
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    @include('partials.licence-field', [
                        'name' => 'shop_act_licence_no',
                        'label' => __('Shop Act licence'),
                        'value' => $company->shop_act_licence_no,
                        'tillName' => 'shop_act_valid_till',
                        'tillValue' => $company->shop_act_valid_till,
                    ])
                    @include('partials.licence-field', [
                        'name' => 'fssai_licence_no',
                        'label' => __('FSSAI licence'),
                        'value' => $company->fssai_licence_no,
                        'tillName' => 'fssai_valid_till',
                        'tillValue' => $company->fssai_valid_till,
                    ])
                    @include('partials.licence-field', [
                        'name' => 'seed_licence_no',
                        'label' => __('Seed licence'),
                        'value' => $company->seed_licence_no,
                        'tillName' => 'seed_licence_valid_till',
                        'tillValue' => $company->seed_licence_valid_till,
                    ])
                    @include('partials.licence-field', [
                        'name' => 'fertilizer_licence_no',
                        'label' => __('Fertilizer licence'),
                        'value' => $company->fertilizer_licence_no,
                        'tillName' => 'fertilizer_licence_valid_till',
                        'tillValue' => $company->fertilizer_licence_valid_till,
                    ])
                    @include('partials.licence-field', [
                        'name' => 'pesticide_licence_no',
                        'label' => __('Pesticide licence'),
                        'value' => $company->pesticide_licence_no,
                        'tillName' => 'pesticide_licence_valid_till',
                        'tillValue' => $company->pesticide_licence_valid_till,
                    ])
                </div>
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Bank & invoice') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('Bank / UPI details and terms — also shown on the client statement link.') }}</p>
            </div>
            <div class="card-body">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-field name="bank_name" :label="__('Bank name')" :value="$company->bank_name" />
                    <x-field name="bank_account_no" :label="__('Account no')" :value="$company->bank_account_no" />
                    <x-field name="bank_ifsc" :label="__('IFSC')" :value="$company->bank_ifsc" />
                    <x-field name="bank_branch" :label="__('Bank branch')" :value="$company->bank_branch" />
                    <x-field name="upi_id" :label="__('UPI ID')" :value="$company->upi_id"
                             :hint="__('Shown on client statement when payment option is on')" />
                    <x-select-field name="default_invoice_print_format" :label="__('Default invoice print format')"
                        :value="old('default_invoice_print_format', $company->default_invoice_print_format ?: 'standard')"
                        :options="\App\Support\PrintFormats::saleLabels()"
                        :hint="__('Staff can still pick another format when printing')" />
                    <x-select-field name="invoice_print_lang" :label="__('Default print language')"
                        :value="old('invoice_print_lang', $company->invoice_print_lang ?: 'auto')"
                        :options="['auto' => __('Follow app language'), 'en' => __('English'), 'mr' => __('Marathi')]"
                        :hint="__('Standard & agro invoices use this unless overridden on Print')" />
                    <div class="md:col-span-3">
                        @include('partials.terms-editor', [
                            'name' => 'invoice_terms',
                            'id' => 'f-invoice_terms',
                            'value' => old('invoice_terms', \App\Support\TermsHtml::fromCompany($company->invoice_terms)),
                            'label' => __('Default invoice terms'),
                            'hint' => __('Used as the starting text on each sale/purchase. Staff can edit per bill before Save & Print.'),
                            'class' => 'space-y-2',
                            'rows' => 8,
                        ])
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="border-b border-sage px-5 py-3">
                <h2 class="card-title">{{ __('Outstanding messaging') }}</h2>
                <p class="text-xs text-ink-soft">{{ __('WhatsApp / SMS-style text for Call & WhatsApp on Outstanding. Tokens: {client} {shop} {amount} {link} {phone}') }}</p>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-ink-soft" for="f-outstanding_wa_template">{{ __('Message template') }}</label>
                    <textarea id="f-outstanding_wa_template" name="outstanding_wa_template" rows="5" class="input w-full font-mono text-sm"
                              placeholder="{{ __('Namaskar {client}, …') }}">{{ old('outstanding_wa_template', $company->outstanding_wa_template) }}</textarea>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="statement_link_enabled" value="0">
                        <input type="checkbox" name="statement_link_enabled" value="1" class="rounded border-sage-dark"
                               @checked(old('statement_link_enabled', $company->statement_link_enabled ?? true))>
                        {{ __('Include statement link by default') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="statement_allow_payment" value="0">
                        <input type="checkbox" name="statement_allow_payment" value="1" class="rounded border-sage-dark"
                               @checked(old('statement_allow_payment', $company->statement_allow_payment ?? true))>
                        {{ __('Show payment details on link') }}
                    </label>
                    <x-field name="statement_link_days" type="number" :label="__('Link valid (days)')"
                             :value="old('statement_link_days', $company->statement_link_days ?? 7)" min="1" max="90" />
                </div>
            </div>
        </section>

        @can('menu', ['settings_company', 'edit'])
            <div><button class="btn-primary">{{ __('Save') }}</button></div>
        @endcan
    </form>
</x-app-layout>
