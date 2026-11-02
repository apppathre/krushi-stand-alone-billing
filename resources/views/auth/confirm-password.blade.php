<x-guest-layout>
    <p class="mb-4 text-sm text-ink-soft">
        {{ __('This is a secure area. Please confirm your password before continuing.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex justify-end">
            <x-primary-button>{{ __('Confirm') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
