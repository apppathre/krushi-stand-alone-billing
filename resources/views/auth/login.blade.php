<x-guest-layout>
    <div class="login-hero mb-6">
        <p class="login-kicker">{{ __('Shop portal') }}</p>
        <h1 class="font-display text-3xl font-semibold text-leaf-dark">{{ __('Sign in') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Welcome back. Sign in to continue billing.') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="login-form space-y-4" autocomplete="on" data-busy-label="{{ __('Signing in…') }}">
        @csrf

        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" data-login-retry hidden role="alert"></div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@shop.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="password-wrap mt-1.5">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                       class="input pr-12" placeholder="{{ __('Your password') }}" />
                <button type="button" class="toggle-pass" @click="show = !show"
                        :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                        :title="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                    <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/>
                        <circle cx="12" cy="12" r="3" stroke-width="1.8"/>
                    </svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.5 10.5A3 3 0 0012 15a3 3 0 002.5-4.5M9.9 5.1A10.5 10.5 0 0112 5c6 0 9.5 7 9.5 7a16.7 16.7 0 01-3.2 4.1M6.1 6.1A16.6 16.6 0 002.5 12S6 19 12 19a10.4 10.4 0 004.2-.9"/>
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-1">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" class="checkbox" name="remember">
                <span class="text-sm font-semibold text-ink-soft">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-semibold text-accent underline-offset-2 hover:text-accent-dark hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full gap-2 py-3.5 text-base" data-busy-label="{{ __('Signing in…') }}">
            <span>{{ __('Log in') }}</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/></svg>
        </button>
    </form>
</x-guest-layout>
