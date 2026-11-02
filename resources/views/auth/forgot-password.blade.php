<x-guest-layout>
    <div class="login-hero mb-6">
        <p class="login-kicker">{{ __('Account recovery') }}</p>
        <h1 class="font-display text-3xl font-semibold text-leaf-dark">{{ __('Forgot your password?') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Enter your email address and we will email you a link to choose a new password.') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="login-form space-y-4" data-busy-label="{{ __('Sending…') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
            <a class="text-sm font-semibold text-accent underline-offset-2 hover:underline" href="{{ route('login') }}">{{ __('Back to sign in') }}</a>
            <button type="submit" class="btn-primary" data-busy-label="{{ __('Sending…') }}">{{ __('Email Password Reset Link') }}</button>
        </div>
    </form>
</x-guest-layout>
