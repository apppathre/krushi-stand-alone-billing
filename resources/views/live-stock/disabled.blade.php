<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Live stock off') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-[#152E23] px-5 text-center text-white">
    <div>
        <h1 class="font-display text-2xl font-semibold">{{ __('Live stock screen is off') }}</h1>
        <p class="mt-2 text-sm text-white/70">{{ __('Enable it in Owner / App control and set a passkey.') }}</p>
    </div>
</body>
</html>
