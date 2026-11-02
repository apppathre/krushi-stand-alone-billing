@php
    $status = session('status');
    $statusText = match ($status) {
        'profile-updated' => __('Profile updated.'),
        'password-updated' => __('Password updated.'),
        default => $status,
    };

    $errorToast = null;
    if ($errors->any()) {
        $first = $errors->first();
        $more = $errors->count() - 1;
        $errorToast = $more > 0
            ? __('Could not save — :msg (+ :n more). Check the form.', ['msg' => $first, 'n' => $more])
            : __('Could not save — :msg', ['msg' => $first]);
    }
@endphp

@if ($statusText)
    <div x-data x-init="$store.ui.toast(@js($statusText), 'success', 5200)" class="sr-only" aria-hidden="true"></div>
@endif

@if ($errorToast)
    <div x-data x-init="$store.ui.toast(@js($errorToast), 'error', 6500)" class="sr-only" aria-hidden="true"></div>
    <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-700 shadow-sm">
        <p class="font-bold">{{ __('Please fix the following:') }}</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
