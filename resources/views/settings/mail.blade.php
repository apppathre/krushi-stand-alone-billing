<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Mail (SMTP)') }}</h1>
    </x-slot>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('settings.mail.update') }}" class="card card-body space-y-4 lg:col-span-2">
            @csrf @method('PUT')
            <p class="text-sm text-ink-soft">{{ __('These settings are used for all outgoing email, including password reset links.') }}</p>

            <x-check name="is_enabled" :label="__('Enable SMTP')" :checked="$mail->is_enabled" />

            <div class="grid gap-4 md:grid-cols-2">
                <x-field name="host" :label="__('SMTP host')" :value="$mail->host" />
                <x-field name="port" type="number" :label="__('Port')" :value="$mail->port" required />
                <x-select-field name="encryption" :label="__('Encryption')" :value="$mail->encryption" :placeholder="__('None')"
                    :options="['tls' => 'TLS (STARTTLS, 587)', 'ssl' => 'SSL (465)']" />
                <x-field name="username" :label="__('Username')" :value="$mail->username" autocomplete="off" />
                <x-field name="password" type="password" :label="__('Password')" autocomplete="new-password"
                    :hint="$mail->password ? __('A password is saved. Leave blank to keep it.') : null" />
                <x-field name="from_address" type="email" :label="__('From address')" :value="$mail->from_address" />
                <x-field name="from_name" :label="__('From name')" :value="$mail->from_name" />
            </div>

            @can('menu', ['settings_mail', 'edit'])
                <div><button class="btn-primary">{{ __('Save') }}</button></div>
            @endcan
        </form>

        @can('menu', ['settings_mail', 'edit'])
            <form method="POST" action="{{ route('settings.mail.test') }}" class="card card-body space-y-3 self-start">
                @csrf
                <h2 class="card-title">{{ __('Send test email') }}</h2>
                <x-field name="test_email" type="email" :label="__('Send to')" :value="auth()->user()->email" required />
                <button class="btn-accent w-full">{{ __('Send test') }}</button>
                <p class="text-xs text-ink-soft">{{ __('Save and enable the settings first.') }}</p>
            </form>
        @endcan
    </div>
</x-app-layout>
