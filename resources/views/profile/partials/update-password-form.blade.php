<section x-data="{ showCur: false, showNew: false, showConf: false }">
    <header class="mb-4">
        <h2 class="font-display text-lg font-semibold text-leaf-dark">{{ __('Change password') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Current password cannot be viewed. Choose a new one with at least 8 characters.') }}</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-4" data-busy-label="{{ __('Updating…') }}">
        @csrf
        @method('put')

        <div>
            <label class="label" for="update_password_current_password">{{ __('Current password') }}</label>
            <div class="password-wrap">
                <input id="update_password_current_password" name="current_password"
                       :type="showCur ? 'text' : 'password'" class="input pr-12" autocomplete="current-password" required>
                <button type="button" class="toggle-pass" @click="showCur = !showCur"
                        :aria-label="showCur ? '{{ __('Hide') }}' : '{{ __('Show') }}'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <label class="label" for="update_password_password">{{ __('New password') }}</label>
            <div class="password-wrap">
                <input id="update_password_password" name="password"
                       :type="showNew ? 'text' : 'password'" class="input pr-12" autocomplete="new-password" required minlength="8">
                <button type="button" class="toggle-pass" @click="showNew = !showNew"
                        :aria-label="showNew ? '{{ __('Hide') }}' : '{{ __('Show') }}'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <label class="label" for="update_password_password_confirmation">{{ __('Confirm new password') }}</label>
            <div class="password-wrap">
                <input id="update_password_password_confirmation" name="password_confirmation"
                       :type="showConf ? 'text' : 'password'" class="input pr-12" autocomplete="new-password" required minlength="8">
                <button type="button" class="toggle-pass" @click="showConf = !showConf"
                        :aria-label="showConf ? '{{ __('Hide') }}' : '{{ __('Show') }}'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                </button>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">{{ __('Update password') }}</button>
            @if (session('status') === 'password-updated')
                <p class="text-sm font-semibold text-leaf">{{ __('Password changed.') }}</p>
            @endif
        </div>
    </form>
</section>
