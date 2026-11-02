<x-mail::message>
# {{ __('Welcome, :name', ['name' => $user->name]) }}

{{ __('Your staff account for :app is ready. Use the details below to sign in.', ['app' => $shopName]) }}

**{{ __('Login email') }}:** {{ $user->email }}  
**{{ __('Password') }}:** {{ $plainPassword }}

<x-mail::button :url="$loginUrl">
{{ __('Sign in') }}
</x-mail::button>

## {{ __('Forgot your password?') }}

1. {{ __('Open the sign-in page:') }} [{{ $loginUrl }}]({{ $loginUrl }})
2. {{ __('Click “Forgot your password?”') }}
3. {{ __('Enter this email (:email) and submit.', ['email' => $user->email]) }}
4. {{ __('Open the reset link we email you and choose a new password.') }}

{{ __('Direct reset page:') }} [{{ $forgotUrl }}]({{ $forgotUrl }})

{{ __('Keep this email private. You can change your password after signing in, or ask an owner to set a new one from Users.') }}

{{ __('Thanks') }},<br>
{{ $shopName }}
</x-mail::message>
