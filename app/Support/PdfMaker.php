<?php

namespace App\Support;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Blade → mPDF with UTF-8 + Devanagari font support.
 */
class PdfMaker
{
    /**
     * @param  array{paper?: string|array{0:float,1:float}, orientation?: string}  $paper
     *        paper: 'a4'|'a5' or [widthMm, heightMm]; orientation: portrait|landscape
     */
    public static function fromView(string $view, array $data, array $paper = [], string $lang = 'en'): Mpdf
    {
        PrintFonts::ensureInstalled();
        $fonts = PrintFonts::mpdfFontConfig();

        $orientation = strtolower((string) ($paper['orientation'] ?? 'portrait')) === 'landscape' ? 'L' : 'P';
        $format = self::resolveFormat($paper['paper'] ?? 'a4', $orientation);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'orientation' => $orientation,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => PrintFonts::tempDir(),
            'fontDir' => $fonts['fontDir'],
            'fontdata' => $fonts['fontdata'],
            'default_font' => PrintFonts::defaultFont($lang),
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetDisplayMode('fullpage');
        $html = view($view, $data)->render();
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    public static function response(Mpdf $mpdf, string $filename, bool $download = false): Response
    {
        $bytes = $mpdf->Output($filename, Destination::STRING_RETURN);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /** @param  string|array{0:int|float,1:int|float,2?:int|float,3?:int|float}  $paper */
    private static function resolveFormat(string|array $paper, string $orientation): string|array
    {
        if (is_array($paper)) {
            // DomPDF used points [x0,y0,x1,y1]; convert custom box to mm width×height.
            if (count($paper) >= 4) {
                $wPt = (float) $paper[2] - (float) $paper[0];
                $hPt = (float) $paper[3] - (float) $paper[1];

                return [round($wPt * 25.4 / 72, 2), round($hPt * 25.4 / 72, 2)];
            }

            // Already mm [w, h]
            return [(float) $paper[0], (float) $paper[1]];
        }

        $key = strtolower($paper);

        return match ($key) {
            'a5' => 'A5',
            'a6' => 'A6',
            default => 'A4',
        };
    }
}
