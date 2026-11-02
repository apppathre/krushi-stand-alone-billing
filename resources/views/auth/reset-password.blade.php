<x-guest-layout>
    <div class="login-hero mb-6">
        <p class="login-kicker">{{ __('Secure reset') }}</p>
        <h1 class="font-display text-3xl font-semibold text-leaf-dark">{{ __('Reset password') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Choose a strong password, then sign in again.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="login-form space-y-4" data-busy-label="{{ __('Saving…') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="password-wrap mt-1.5">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password"
                       class="input pr-12" />
                <button type="button" class="toggle-pass" @click="show = !show"
                        :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                    <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.5 10.5A3 3 0 0012 15a3 3 0 002.5-4.5M9.9 5.1A10.5 10.5 0 0112 5c6 0 9.5 7 9.5 7a16.7 16.7 0 01-3.2 4.1M6.1 6.1A16.6 16.6 0 002.5 12S6 19 12 19a10.4 10.4 0 004.2-.9"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <div class="password-wrap mt-1.5">
                <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                       class="input pr-12" />
                <button type="button" class="toggle-pass" @click="show = !show"
                        :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                    <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.5 10.5A3 3 0 0012 15a3 3 0 002.5-4.5M9.9 5.1A10.5 10.5 0 0112 5c6 0 9.5 7 9.5 7a16.7 16.7 0 01-3.2 4.1M6.1 6.1A16.6 16.6 0 002.5 12S6 19 12 19a10.4 10.4 0 004.2-.9"/></svg>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-primary w-full py-3.5 text-base" data-busy-label="{{ __('Saving…') }}">{{ __('Reset Password') }}</button>
    </form>
</x-guest-layout>
