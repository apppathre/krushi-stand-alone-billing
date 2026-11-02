<?php

namespace App\Support;

use App\Models\CompanyProfile;

class PrintFormats
{
    /** @return array<string, array<string, mixed>> */
    public static function forSale(): array
    {
        return config('print_formats.sale', []);
    }

    public static function saleLabels(): array
    {
        $out = [];
        foreach (self::forSale() as $key => $meta) {
            $out[$key] = app()->getLocale() === 'mr'
                ? ($meta['label_mr'] ?? $meta['label'] ?? $key)
                : ($meta['label'] ?? $key);
        }

        return $out;
    }

    public static function defaultSaleFormat(?CompanyProfile $company = null): string
    {
        $company ??= CompanyProfile::current();
        $key = (string) ($company->default_invoice_print_format ?: 'standard');

        return array_key_exists($key, self::forSale()) ? $key : 'standard';
    }

    public static function defaultSaleLang(?CompanyProfile $company = null): string
    {
        $company ??= CompanyProfile::current();
        $lang = (string) ($company->invoice_print_lang ?: 'auto');
        if ($lang === 'auto') {
            return app()->getLocale() === 'mr' ? 'mr' : 'en';
        }

        return in_array($lang, ['en', 'mr'], true) ? $lang : 'en';
    }

    public static function defaultSaleSize(?CompanyProfile $company = null): string
    {
        $company ??= CompanyProfile::current();
        $size = (string) ($company->default_print_size ?: 'a4');

        return in_array($size, ['a4', 'half', 'quarter'], true) ? $size : 'a4';
    }

    public static function resolveSale(string $format): array
    {
        $meta = self::forSale()[$format] ?? self::forSale()['standard'] ?? [];

        return $meta + [
            'view' => 'sales.pdf',
            'sizes' => ['a4'],
            'default_size' => 'a4',
            'orientation' => 'portrait',
        ];
    }
}
