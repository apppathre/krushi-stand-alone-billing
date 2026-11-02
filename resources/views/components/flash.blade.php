@php
    $status = session('status');
    $statusText = match ($status) {
        'profile-updated' => __('Profile updated.'),
        'password-updated' => __('Password updated.'),
        default => $status,
    };
@endphp

@if ($statusText)
    <div x-data x-init="$store.ui.toast(@js($statusText), 'success')" class="sr-only" aria-hidden="true"></div>
@endif

@if ($errors->any())
    <div x-data x-init="$store.ui.toast(@js($errors->first()), 'error')" class="sr-only" aria-hidden="true"></div>
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-700 shadow-sm">
        <p class="font-bold">{{ __('Please fix the following:') }}</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
