<?php

namespace App\Support;

/** Default + per-voucher terms (TinyMCE HTML) for invoice print. */
class TermsHtml
{
    private const ALLOWED = '<p><br><br/><div><span><ul><ol><li><strong><b><em><i><u><h3><h4>';

    /** Company plain text → simple HTML for the editor. */
    public static function fromCompany(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if (str_contains($text, '<')) {
            return self::sanitize($text);
        }

        $parts = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $html = '';
        foreach ($parts as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $html .= '<p>'.e($line).'</p>';
        }

        return $html !== '' ? $html : '<p>'.e($text).'</p>';
    }

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // Drop scripts/styles/event handlers via strip_tags allow-list.
        $clean = strip_tags($html, self::ALLOWED);
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;

        return trim($clean);
    }

    /** Print body: voucher terms, else company default. */
    public static function forPrint(?string $voucherTerms, ?string $companyTerms): string
    {
        $html = self::sanitize($voucherTerms);
        if ($html !== '') {
            return $html;
        }

        return self::fromCompany($companyTerms);
    }
}
