<section>
    <header class="mb-4">
        <h2 class="font-display text-lg font-semibold text-leaf-dark">{{ __('Profile information') }}</h2>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Name, phone, language, and photo used across the shop.') }}</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4" data-busy-label="{{ __('Saving…') }}">
        @csrf
        @method('patch')
        <input type="hidden" name="_tab" value="profile">

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label">{{ __('Profile photo') }}</label>
                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-sage bg-cream/40 p-3">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="" class="h-20 w-20 rounded-xl object-cover">
                    @else
                        <span class="topbar-avatar !h-20 !w-20 !rounded-xl !text-2xl">{{ $user->initials() }}</span>
                    @endif
                    <div class="min-w-0 flex-1 space-y-2">
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="input text-sm">
                        <p class="text-xs text-ink-soft">{{ __('JPG, PNG, GIF or WebP. Cropped square to ~256px.') }}</p>
                        @if ($user->avatar_path)
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-ink-soft">
                                <input type="checkbox" name="remove_avatar" value="1" class="checkbox">
                                {{ __('Remove current photo') }}
                            </label>
                        @endif
                        @error('avatar') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <x-field name="name" :label="__('Name')" :value="old('name', $user->name)" required autocomplete="name" />
            <x-field name="phone" :label="__('Phone')" :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-field name="email" type="email" :label="__('Email')" :value="old('email', $user->email)" required autocomplete="username" />
            <x-select-field name="locale" :label="__('Language')" :value="old('locale', $user->locale ?? 'en')"
                :options="['en' => 'English', 'mr' => 'मराठी']" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">{{ __('Save profile') }}</button>
            @if (session('status') === 'profile-updated')
                <p class="text-sm font-semibold text-leaf">{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
