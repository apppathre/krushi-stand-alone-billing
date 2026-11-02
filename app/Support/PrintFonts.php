<?php

namespace App\Support;

/**
 * Marathi / Devanagari fonts for mPDF.
 * Source of truth ships in the repo: resources/fonts/devanagari/*.ttf
 * Install / maintenance copies them into storage/fonts for mPDF.
 */
class PrintFonts
{
    /** mPDF fontdata key (lowercase, no spaces). */
    public const FAMILY = 'notosansdevanagari';

    /** @var list<string> */
    public const FILES = [
        'NotoSansDevanagari-Regular.ttf',
        'NotoSansDevanagari-Bold.ttf',
    ];

    public static function resourceDir(): string
    {
        return resource_path('fonts/devanagari');
    }

    public static function storageDir(): string
    {
        return storage_path('fonts');
    }

    public static function tempDir(): string
    {
        $dir = storage_path('app/mpdf-temp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function regularPath(): string
    {
        return self::resolvePath('NotoSansDevanagari-Regular.ttf');
    }

    public static function boldPath(): string
    {
        return self::resolvePath('NotoSansDevanagari-Bold.ttf');
    }

    /** True when both regular + bold TTFs are readable (storage or resources). */
    public static function marathiAvailable(): bool
    {
        return is_file(self::regularPath()) && is_file(self::boldPath());
    }

    /**
     * CSS font-family for invoice PDF body (mPDF keys).
     * Prefer Noto for Marathi and mixed EN/MR product names; DejaVu for Latin-only.
     */
    public static function cssFamily(string $lang = 'en'): string
    {
        if ($lang === 'mr' && self::marathiAvailable()) {
            return "'".self::FAMILY."', dejavusans, sans-serif";
        }

        if (self::marathiAvailable()) {
            return "dejavusans, '".self::FAMILY."', sans-serif";
        }

        return 'dejavusans, sans-serif';
    }

    /** Default mPDF body font for this language. */
    public static function defaultFont(string $lang = 'en'): string
    {
        if ($lang === 'mr' && self::marathiAvailable()) {
            return self::FAMILY;
        }

        return 'dejavusans';
    }

    /**
     * Copy bundled TTFs into storage/fonts (idempotent).
     * Pass $force=true to overwrite storage copies (repair / re-install).
     */
    public static function ensureInstalled(bool $force = false): bool
    {
        return self::install($force)['ok'];
    }

    /**
     * Install / re-install PDF fonts from resources → storage.
     *
     * @return array{ok:bool, message:string, copied:list<string>, missing:list<string>, source_ok:bool, storage_ok:bool}
     */
    public static function install(bool $force = false): array
    {
        self::tempDir();

        if (! is_dir(self::storageDir())) {
            @mkdir(self::storageDir(), 0775, true);
        }

        $copied = [];
        $missing = [];

        foreach (self::FILES as $file) {
            $src = self::resourceDir().DIRECTORY_SEPARATOR.$file;
            $dest = self::storageDir().DIRECTORY_SEPARATOR.$file;

            if (! is_file($src)) {
                $missing[] = $file;

                continue;
            }

            if ($force || ! is_file($dest)) {
                if (! @copy($src, $dest)) {
                    $missing[] = $file;

                    continue;
                }
                $copied[] = $file;
            }
        }

        $sourceOk = self::bundledSourceOk();
        $storageOk = self::storageOk();
        $ok = $sourceOk && ($storageOk || self::marathiAvailable());

        if (! $sourceOk) {
            $message = __('Marathi PDF fonts missing from code (resources/fonts/devanagari). Push/pull the Noto TTF files with the project.');
        } elseif ($missing !== []) {
            $message = __('Could not copy PDF fonts to storage: :list', ['list' => implode(', ', $missing)]);
            $ok = false;
        } elseif ($copied !== []) {
            $message = __('Marathi PDF fonts installed (:n file(s) copied to storage/fonts).', ['n' => count($copied)]);
        } else {
            $message = __('Marathi PDF fonts already ready (mPDF + Noto Sans Devanagari).');
        }

        return [
            'ok' => $ok && $missing === [],
            'message' => $message,
            'copied' => $copied,
            'missing' => $missing,
            'source_ok' => $sourceOk,
            'storage_ok' => $storageOk,
        ];
    }

    /** Both TTFs present under resources/fonts/devanagari (shipped with code). */
    public static function bundledSourceOk(): bool
    {
        foreach (self::FILES as $file) {
            if (! is_file(self::resourceDir().DIRECTORY_SEPARATOR.$file)) {
                return false;
            }
        }

        return true;
    }

    /** Both TTFs present under storage/fonts. */
    public static function storageOk(): bool
    {
        foreach (self::FILES as $file) {
            if (! is_file(self::storageDir().DIRECTORY_SEPARATOR.$file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extra fontDir paths + fontdata for Mpdf constructor.
     *
     * @return array{fontDir: list<string>, fontdata: array<string, array<string, string>>}
     */
    public static function mpdfFontConfig(): array
    {
        $defaults = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];
        $fontDirs[] = self::storageDir();
        $fontDirs[] = self::resourceDir();

        $fontDefaults = (new \Mpdf\Config\FontVariables)->getDefaults();
        $fontdata = $fontDefaults['fontdata'];

        if (self::ensureInstalled()) {
            // Noto Sans Devanagari covers Marathi glyphs. Full useOTL (0xFF) trips
            // mPDF on GPOS Lookup Type 5 Format 3 in current Noto builds — omit OTL
            // so metrics load; Unicode codepoints still print (far better than DomPDF).
            $fontdata[self::FAMILY] = [
                'R' => 'NotoSansDevanagari-Regular.ttf',
                'B' => 'NotoSansDevanagari-Bold.ttf',
            ];
        }

        return [
            'fontDir' => $fontDirs,
            'fontdata' => $fontdata,
        ];
    }

    /**
     * @return array{available:bool,family:string,label:string,source_ok:bool,storage_ok:bool,source_path:string,storage_path:string}
     */
    public static function statusForUi(): array
    {
        $sourceOk = self::bundledSourceOk();
        $storageOk = self::storageOk();
        $ok = self::marathiAvailable();

        if ($ok) {
            $label = __('Marathi PDF font ready (Noto Sans Devanagari / mPDF)');
        } elseif (! $sourceOk) {
            $label = __('Marathi PDF fonts missing from code — push resources/fonts/devanagari/*.ttf with the project');
        } else {
            $label = __('Marathi PDF fonts not copied to storage — run Install / Server maintenance → Install PDF fonts');
        }

        return [
            'available' => $ok,
            'family' => self::FAMILY,
            'label' => $label,
            'source_ok' => $sourceOk,
            'storage_ok' => $storageOk,
            'source_path' => self::resourceDir(),
            'storage_path' => self::storageDir(),
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
