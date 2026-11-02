{{-- Print format picker + Marathi font-aware dialog. --}}
@php
    $formats = \App\Support\PrintFormats::forSale();
    $defaultFormat = \App\Support\PrintFormats::defaultSaleFormat();
    $defaultLang = \App\Support\PrintFormats::defaultSaleLang();
    $defaultSize = \App\Support\PrintFormats::defaultSaleSize();
    $defaultMeta = $formats[$defaultFormat] ?? [];
    $allowedDefaultSizes = $defaultMeta['sizes'] ?? ['a4'];
    if (! in_array($defaultSize, $allowedDefaultSizes, true)) {
        $defaultSize = $defaultMeta['default_size'] ?? $allowedDefaultSizes[0];
    }
    $mrFontOk = \App\Support\PrintFonts::ensureInstalled();
@endphp
<div
    class="relative"
    x-data="invoicePrintMenu({
        mrFontOk: @js($mrFontOk),
        defaultLang: @js($defaultLang),
        defaultFormat: @js($defaultFormat),
        defaultSize: @js($defaultSize),
        pdfBase: @js(route('sales.pdf', $sale)),
        browserBase: @js(route('sales.print', $sale)),
        dcBase: @js(route('sales.dc', $sale)),
    })"
    @click.outside="open = false; dialog = false"
>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn-accent" @click="quickPrint()">{{ __('Print invoice') }}</button>
        <a href="{{ route('sales.dc', [$sale, 'size' => $defaultSize, 'lang' => $defaultLang]) }}"
           target="_blank" class="btn-secondary">{{ __('Print DC') }}</a>
        <button type="button" @click="open = !open" class="btn-secondary btn-sm">{{ __('Options') }} ▾</button>
    </div>

    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-80 rounded-xl border border-sage-dark bg-white py-1 shadow-lg">
        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">
            {{ __('Default') }}: {{ $formats[$defaultFormat]['label'] ?? $defaultFormat }} · {{ strtoupper($defaultSize) }} · {{ strtoupper($defaultLang) }}
        </p>
        <p class="px-4 pb-1 text-[10px] {{ $mrFontOk ? 'text-leaf' : 'text-amber-700' }}">
            {{ $mrFontOk ? __('Marathi PDF font ready') : __('Marathi PDF font missing — Browser print recommended') }}
        </p>

        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">{{ __('Format') }}</p>
        @foreach ($formats as $key => $meta)
            @php
                $label = app()->getLocale() === 'mr' ? ($meta['label_mr'] ?? $meta['label']) : ($meta['label'] ?? $key);
                $sizes = $meta['sizes'] ?? ['a4'];
            @endphp
            <div class="border-t border-sage/70 px-2 py-1">
                <p class="px-2 text-xs font-semibold text-leaf-dark">
                    {{ $label }}
                    @if ($key === $defaultFormat)<span class="text-[10px] font-normal text-ink-soft">({{ __('default') }})</span>@endif
                </p>
                <div class="mt-0.5">
                    @foreach ($sizes as $size)
                        <button type="button"
                                class="block w-full rounded-lg px-2 py-1.5 text-left text-sm hover:bg-sage {{ $key === $defaultFormat && $size === $defaultSize ? 'bg-sage/40 font-semibold' : '' }}"
                                @click="choosePdf(@js($key), @js($size), defaultLang)">
                            @if ($size === 'a4') {{ __('A4') }}
                            @elseif ($size === 'half') {{ __('Half page (A5)') }}
                            @elseif ($size === 'quarter') {{ __('1/4 page') }}
                            @else {{ strtoupper($size) }}
                            @endif
                            · {{ $defaultLang === 'mr' ? __('Marathi') : __('English') }}
                        </button>
                    @endforeach
                    <button type="button" class="block w-full rounded-lg px-2 py-1 text-left text-xs text-ink-soft hover:bg-sage"
                            @click="choosePdf(@js($key), @js($meta['default_size'] ?? 'a4'), defaultLang === 'mr' ? 'en' : 'mr')">
                        {{ $defaultLang === 'mr' ? __('Open in English') : __('Open in Marathi') }}
                    </button>
                </div>
            </div>
        @endforeach

        <div class="my-1 border-t border-sage"></div>
        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">{{ __('Best for Marathi') }}</p>
        <button type="button" class="block w-full px-4 py-2 text-left text-sm font-semibold text-leaf hover:bg-sage"
                @click="openBrowser(defaultFormat, defaultSize, 'mr')">
            {{ __('Browser print (clear Marathi)') }}
        </button>

        <div class="my-1 border-t border-sage"></div>
        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">{{ __('Warehouse') }}</p>
        <a href="{{ route('sales.dc', [$sale, 'size' => $defaultSize, 'lang' => $defaultLang]) }}"
           target="_blank"
           class="block px-4 py-2 text-sm hover:bg-sage">{{ __('Delivery Challan / Note') }}</a>
        <div class="my-1 border-t border-sage"></div>
        <a href="{{ route('settings.app-control') }}" class="block px-4 py-2 text-xs text-ink-soft hover:bg-sage">{{ __('Change print defaults…') }}</a>
    </div>

    {{-- Dialog when Marathi PDF font is not available --}}
    <div x-show="dialog" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="dialog = false">
        <div class="w-full max-w-md rounded-2xl border border-sage-dark bg-white p-5 shadow-xl" @click.stop>
            <h3 class="font-display text-lg font-semibold text-leaf-dark">{{ __('Marathi print') }}</h3>
            <p class="mt-2 text-sm text-ink-soft">
                {{ __('PDF Marathi needs a Devanagari font on the server. Choose the best option:') }}
            </p>
            <div class="mt-4 space-y-2">
                <button type="button" class="btn-primary w-full justify-center" @click="confirmBrowser()">
                    {{ __('Browser print — clearest Marathi') }}
                </button>
                <button type="button" class="btn-secondary w-full justify-center" @click="confirmEnglishPdf()">
                    {{ __('English PDF (always works)') }}
                </button>
                <button type="button" class="btn-secondary w-full justify-center" x-show="mrFontOk" @click="confirmMrPdf()">
                    {{ __('Marathi PDF') }}
                </button>
            </div>
            <button type="button" class="mt-3 w-full text-center text-sm text-ink-soft" @click="dialog = false">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>
