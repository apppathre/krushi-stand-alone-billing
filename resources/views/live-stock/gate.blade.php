<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Live stock') }} · {{ $company->display_name }}</title>
    @include('layouts.partials.fonts')
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-[#1B3D2E] via-[#152E23] to-[#0F2219] text-white antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-white/55">{{ $company->display_name }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold">{{ $company->live_stock_title ?: __('Live stock') }}</h1>
        <p class="mt-2 text-sm text-white/70">{{ __('Enter the shop screen passkey. No staff login needed.') }}</p>

        <form method="POST" action="{{ route('live-stock.unlock') }}" class="mt-8 space-y-4 rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/70" for="passkey">{{ __('Passkey') }}</label>
                <input id="passkey" type="password" name="passkey" required autofocus
                       class="w-full rounded-xl border border-white/20 bg-white/95 px-3 py-2.5 text-leaf-dark outline-none focus:ring-2 focus:ring-leaf/40"
                       autocomplete="current-password">
                @error('passkey') <p class="mt-1 text-sm text-amber-200">{{ $message }}</p> @enderror
            </div>
            <button class="w-full rounded-xl bg-[#C4784A] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#A8613A]">{{ __('Open board') }}</button>
        </form>
        <p class="mt-6 text-center text-xs text-white/45">{{ __('Owner can turn this off under App control.') }}</p>
    </div>
</body>
</html>
