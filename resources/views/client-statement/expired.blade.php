<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Link expired') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background:#f4f8f5; color:#1a2e24; display:grid; place-items:center; min-height:100vh; margin:0; padding:1rem; }
        .box { background:#fff; border:1px solid #d7e4dc; border-radius:16px; padding:1.5rem; max-width:24rem; text-align:center; }
        h1 { margin:0 0 .5rem; font-size:1.2rem; color:#1f6b4a; }
        p { margin:0; color:#5a6f64; font-size:.95rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>{{ __('Link expired or invalid') }}</h1>
    <p>{{ __('Ask the shop to send a fresh statement link.') }}</p>
</div>
</body>
</html>
