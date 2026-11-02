<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

/**
 * Marathi / Devanagari fonts for DomPDF.
 * DejaVu Sans does not cover Marathi — Noto Sans Devanagari is bundled for PDF.
 */
class PrintFonts
{
    public const FAMILY = 'NotoSansDevanagari';

    public static function resourceDir(): string
    {
        return resource_path('fonts/devanagari');
    }

    public static function storageDir(): string
    {
        return storage_path('fonts');
    }

    public static function regularPath(): string
    {
        return self::resolvePath('NotoSansDevanagari-Regular.ttf');
    }

    public static function boldPath(): string
    {
        return self::resolvePath('NotoSansDevanagari-Bold.ttf');
    }

    /** True when both regular + bold TTFs are on disk. */
    public static function marathiAvailable(): bool
    {
        return is_file(self::regularPath()) && is_file(self::boldPath());
    }

    /**
     * CSS font-family for invoice PDF body.
     * Marathi → Noto when available; else DejaVu (Latin only — UI should warn).
     */
    public static function cssFamily(string $lang = 'en'): string
    {
        if ($lang === 'mr' && self::marathiAvailable()) {
            return "'".self::FAMILY."', DejaVu Sans, sans-serif";
        }

        return 'DejaVu Sans, sans-serif';
    }

    /** Ensure TTFs exist under storage/fonts (copy from resources if needed). */
    public static function ensureInstalled(): bool
    {
        if (! is_dir(self::storageDir())) {
            @mkdir(self::storageDir(), 0775, true);
        }

        foreach (['NotoSansDevanagari-Regular.ttf', 'NotoSansDevanagari-Bold.ttf'] as $file) {
            $dest = self::storageDir().DIRECTORY_SEPARATOR.$file;
            if (is_file($dest)) {
                continue;
            }
            $src = self::resourceDir().DIRECTORY_SEPARATOR.$file;
            if (! is_file($src)) {
                return false;
            }
            if (! @copy($src, $dest)) {
                return false;
            }
        }

        return self::marathiAvailable();
    }

    /** Register Devanagari fonts with the DomPDF instance used for this document. */
    public static function applyToPdf(DomPdfWrapper $pdf): void
    {
        if (! self::ensureInstalled()) {
            return;
        }

        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $fontDir = self::storageDir();
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontDir);
        $options->setChroot(array_values(array_unique(array_filter([
            base_path(),
            public_path(),
            storage_path(),
            $fontDir,
            self::resourceDir(),
        ]))));

        $metrics = $dompdf->getFontMetrics();
        // DomPDF registerFont expects a URL (file:// for local TTFs).
        $metrics->registerFont(
            ['family' => self::FAMILY, 'style' => 'normal', 'weight' => 'normal'],
            self::fileUrl(self::regularPath())
        );
        $metrics->registerFont(
            ['family' => self::FAMILY, 'style' => 'normal', 'weight' => 'bold'],
            self::fileUrl(self::boldPath())
        );
    }

    /** Absolute path → file:// URL DomPDF can load. */
    public static function fileUrl(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        if (! str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized; // Windows drive letter → /C:/...
        }

        return 'file://'.$normalized;
    }

    /** @return array{available:bool,family:string,label:string} */
    public static function statusForUi(): array
    {
        $ok = self::ensureInstalled();

        return [
            'available' => $ok,
            'family' => self::FAMILY,
            'label' => $ok
                ? __('Marathi PDF font ready (Noto Sans Devanagari)')
                : __('Marathi PDF font missing — use Browser print or English PDF'),
        ];
    }

    private static function resolvePath(string $file): string
    {
        $storage = self::storageDir().DIRECTORY_SEPARATOR.$file;
        if (is_file($storage)) {
            return $storage;
        }

        return self::resourceDir().DIRECTORY_SEPARATOR.$file;
    }
}
