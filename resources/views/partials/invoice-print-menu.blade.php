{{-- Print format picker for a sale invoice. --}}
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
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sales.pdf', [$sale, 'format' => $defaultFormat, 'size' => $defaultSize, 'lang' => $defaultLang]) }}"
           target="_blank" class="btn-accent">{{ __('Print invoice') }}</a>
        <a href="{{ route('sales.dc', [$sale, 'size' => $defaultSize, 'lang' => $defaultLang]) }}"
           target="_blank" class="btn-secondary">{{ __('Print DC') }}</a>
        <button type="button" @click="open = !open" class="btn-secondary btn-sm">{{ __('Options') }} ▾</button>
    </div>
    <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-72 rounded-xl border border-sage-dark bg-white py-1 shadow-lg">
        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">
            {{ __('Default') }}: {{ $formats[$defaultFormat]['label'] ?? $defaultFormat }} · {{ strtoupper($defaultSize) }} · {{ strtoupper($defaultLang) }}
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
                        <a href="{{ route('sales.pdf', [$sale, 'format' => $key, 'size' => $size, 'lang' => $defaultLang]) }}"
                           target="_blank"
                           class="block rounded-lg px-2 py-1.5 text-sm hover:bg-sage {{ $key === $defaultFormat && $size === $defaultSize ? 'bg-sage/40 font-semibold' : '' }}">
                            @if ($size === 'a4') {{ __('A4') }}
                            @elseif ($size === 'half') {{ __('Half page (A5)') }}
                            @elseif ($size === 'quarter') {{ __('1/4 page') }}
                            @else {{ strtoupper($size) }}
                            @endif
                            · {{ $defaultLang === 'mr' ? __('Marathi') : __('English') }}
                        </a>
                    @endforeach
                    <a href="{{ route('sales.pdf', [$sale, 'format' => $key, 'size' => ($meta['default_size'] ?? 'a4'), 'lang' => $defaultLang === 'mr' ? 'en' : 'mr']) }}"
                       target="_blank"
                       class="block rounded-lg px-2 py-1 text-xs text-ink-soft hover:bg-sage">
                        {{ $defaultLang === 'mr' ? __('Open in English') : __('Open in Marathi') }}
                    </a>
                </div>
            </div>
        @endforeach
        <div class="my-1 border-t border-sage"></div>
        <p class="px-4 py-1.5 text-[10px] font-bold uppercase tracking-wide text-ink-soft">{{ __('Warehouse') }}</p>
        <a href="{{ route('sales.dc', [$sale, 'size' => $defaultSize, 'lang' => $defaultLang]) }}"
           target="_blank"
           class="block px-4 py-2 text-sm hover:bg-sage">{{ __('Delivery Challan / Note') }}</a>
        <a href="{{ route('sales.dc', [$sale, 'size' => $defaultSize, 'lang' => $defaultLang, 'download' => 1]) }}"
           class="block px-4 py-1.5 text-xs text-ink-soft hover:bg-sage">{{ __('Download Delivery Challan') }}</a>
        <div class="my-1 border-t border-sage"></div>
        <a href="{{ route('sales.pdf', [$sale, 'format' => $defaultFormat, 'size' => $defaultSize, 'lang' => $defaultLang, 'download' => 1]) }}"
           class="block px-4 py-2 text-sm hover:bg-sage">{{ __('Download default PDF') }}</a>
        <a href="{{ route('settings.app-control') }}" class="block px-4 py-2 text-xs text-ink-soft hover:bg-sage">{{ __('Change print defaults…') }}</a>
    </div>
</div>
