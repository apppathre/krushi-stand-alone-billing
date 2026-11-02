<form method="POST"
      action="{{ route('users.password.update', $user) }}"
      data-ajax-form
      class="space-y-5"
      x-data="{ show: false }">
    @csrf
    @method('PUT')

    <div data-form-errors class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" hidden></div>

    <div class="form-callout" role="note">
        <p class="form-callout__title">{{ __('Set password') }}</p>
        <p class="mb-0">{{ __('Set a new password for :name. The current password cannot be viewed.', ['name' => $user->name]) }}</p>
    </div>

    <div>
        <label class="label" for="password">{{ __('New password') }}</label>
        <div class="password-wrap">
            <input id="password" name="password" :type="show ? 'text' : 'password'" required minlength="8" autocomplete="new-password" class="input pr-12" />
            <button type="button" class="toggle-pass" @click="show = !show"
                    :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'">
                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3l18 18M10.5 10.5A3 3 0 0012 15a3 3 0 002.5-4.5M9.9 5.1A10.5 10.5 0 0112 5c6 0 9.5 7 9.5 7a16.7 16.7 0 01-3.2 4.1M6.1 6.1A16.6 16.6 0 002.5 12S6 19 12 19a10.4 10.4 0 004.2-.9"/></svg>
            </button>
        </div>
    </div>

    <div>
        <label class="label" for="password_confirmation">{{ __('Confirm password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="input" />
    </div>

    <div class="flex flex-wrap gap-2 border-t border-sage pt-4">
        <button type="submit" class="btn-primary">{{ __('Update password') }}</button>
        <button type="button" class="btn-secondary" onclick="window.Alpine && Alpine.store('ui').closeDrawer()">{{ __('Cancel') }}</button>
    </div>
</form>
