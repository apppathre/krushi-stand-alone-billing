@php
    $lang = $printLang ?? 'en';
    $size = $printSize ?? 'a4';
    $pdfUrl = route('purchases.pdf', [$purchase, 'lang' => $lang, 'size' => $size]);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $purchase->purchase_no }} — {{ __('Print') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #eef4ef; color: #1F2A24; }
        .toolbar {
            position: sticky; top: 0; z-index: 5;
            display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; background: #fff; border-bottom: 1px solid #CFE0D4;
        }
        .toolbar h1 { margin: 0; font-size: 1rem; color: #214D39; }
        .btns { display: flex; flex-wrap: wrap; gap: .4rem; }
        .btn {
            appearance: none; border: 1px solid #CFE0D4; background: #fff; color: #1F2A24;
            border-radius: 10px; padding: .45rem .85rem; font: inherit; font-weight: 650; cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: #2F6B4F; border-color: #2F6B4F; color: #fff; }
        iframe { width: 100%; height: calc(100vh - 58px); border: 0; background: #fff; }
        @media print {
            .toolbar { display: none !important; }
            iframe { height: 100vh; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>{{ __('Purchase bill') }} — {{ $purchase->purchase_no }}</h1>
        <div class="btns">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('bill-frame').contentWindow.print()">{{ __('Print') }}</button>
            <a class="btn" href="{{ $pdfUrl }}" target="_blank">{{ __('Open PDF') }}</a>
            <a class="btn" href="{{ route('purchases.pdf', [$purchase, 'lang' => $lang, 'size' => $size, 'download' => 1]) }}">{{ __('Download PDF') }}</a>
            <a class="btn" href="{{ route('purchases.show', $purchase) }}">{{ __('Back') }}</a>
        </div>
    </div>
    <iframe id="bill-frame" src="{{ $pdfUrl }}" title="{{ $purchase->purchase_no }}"></iframe>
    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
                const frame = document.getElementById('bill-frame');
                frame.addEventListener('load', () => setTimeout(() => {
                    try { frame.contentWindow.print(); } catch (e) {}
                }, 400));
            }
        });
    </script>
</body>
</html>
