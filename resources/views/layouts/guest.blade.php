@php
    $companyName = isset($shellCompany) ? $shellCompany->display_name : config('app.name');
    $tagline = isset($shellCompany) && $shellCompany->tagline ? $shellCompany->tagline : __('Seeds · Fertilizer · Pesticide');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $companyName }}</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body antialiased">
    <div class="auth-split">
        <aside class="auth-split-visual" aria-hidden="true">
            <div class="auth-split-photo"></div>
            <div class="auth-split-glow"></div>
            <div class="auth-split-copy">
                <div class="auth-split-brand">
                    <span class="auth-logo-wrap">
                        <x-application-logo class="h-11 w-11" />
                    </span>
                    <div class="min-w-0">
                        <strong>{{ $companyName }}</strong>
                        <span>{{ __('Krushi Seva') }}</span>
                    </div>
                </div>
                <p class="auth-split-kicker">{{ $tagline }}</p>
                <h2>{!! __('Shop billing<br>on one desk.') !!}</h2>
                <p class="auth-split-lead">{{ __('GST billing, batch stock, godowns, ledgers and staff roles — built for your agri shop.') }}</p>
                <div class="auth-module-grid">
                    <div class="auth-module-card">
                        <span class="auth-module-ico is-accent"><x-icon name="sales" class="h-4 w-4" /></span>
                        <div><strong>{{ __('Sales & GST') }}</strong><small>{{ __('Invoices with CGST / SGST / IGST') }}</small></div>
                    </div>
                    <div class="auth-module-card">
                        <span class="auth-module-ico is-leaf"><x-icon name="stock" class="h-4 w-4" /></span>
                        <div><strong>{{ __('Batch stock') }}</strong><small>{{ __('Expiry & place-wise qty') }}</small></div>
                    </div>
                    <div class="auth-module-card">
                        <span class="auth-module-ico is-leaf"><x-icon name="ledger" class="h-4 w-4" /></span>
                        <div><strong>{{ __('Accounts') }}</strong><small>{{ __('Party ledgers & day book') }}</small></div>
                    </div>
                    <div class="auth-module-card">
                        <span class="auth-module-ico is-accent"><x-icon name="shield" class="h-4 w-4" /></span>
                        <div><strong>{{ __('Staff & roles') }}</strong><small>{{ __('Menu permissions per person') }}</small></div>
                    </div>
                </div>
            </div>
        </aside>

        <section class="auth-split-panel">
            <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
                <x-lang-switch />
            </div>
            <div class="auth-split-card">
                {{ $slot }}
            </div>
            <p class="mt-6 text-center text-xs text-ink-soft">
                {{ __('Secure shop billing') }} · {{ now()->format('Y') }}
            </p>
        </section>
    </div>
</body>
</html>
