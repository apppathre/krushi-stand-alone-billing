@php
    $tab = old('_tab', session('profile_tab', $tab ?? 'profile'));
    if (! in_array($tab, ['profile', 'password'], true)) {
        $tab = 'profile';
    }
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('My Profile') }}</h1>
            <p class="text-xs text-ink-soft">{{ __('Update details, photo, language, and password.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4" x-data="{
        tab: '{{ $tab }}',
        init() {
            if (window.location.hash === '#password') this.tab = 'password';
        }
    }">
        <div class="card card-body flex flex-wrap items-center gap-4">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-16 w-16 rounded-xl object-cover ring-2 ring-sage">
            @else
                <span class="topbar-avatar !h-16 !w-16 !rounded-xl !text-xl">{{ $user->initials() }}</span>
            @endif
            <div>
                <p class="font-display text-lg font-semibold text-leaf-dark">{{ $user->name }}</p>
                <p class="text-sm text-ink-soft">{{ $user->email }}@if ($user->phone) · {{ $user->phone }}@endif</p>
                <p class="text-xs text-ink-soft">{{ $user->role?->display_name }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-sage pb-2">
            <button type="button" class="btn-sm" :class="tab === 'profile' ? 'btn-primary' : 'btn-secondary'" @click="tab = 'profile'; window.location.hash = 'profile'">{{ __('Profile') }}</button>
            <button type="button" class="btn-sm" :class="tab === 'password' ? 'btn-primary' : 'btn-secondary'" @click="tab = 'password'; window.location.hash = 'password'">{{ __('Password') }}</button>
        </div>

        <div class="card card-body" x-show="tab === 'profile'" x-cloak>
            @include('profile.partials.update-profile-information-form')
        </div>

        <div id="password" class="card card-body" x-show="tab === 'password'" x-cloak>
            @include('profile.partials.update-password-form')
        </div>
    </div>
</x-app-layout>
