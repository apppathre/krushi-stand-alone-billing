<?php

namespace App\Support;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lightweight CSV / SpreadsheetML (.xls) downloads for on-screen reports.
 */
class ReportTableExport
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function download(string $format, string $filename, array $headers, array $rows): Response|StreamedResponse
    {
        $format = strtolower($format);
        $base = preg_replace('/\.(csv|xls)$/i', '', $filename) ?: 'report';

        if ($format === 'xls') {
            return response(self::xlsXml($base, $headers, $rows), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$base.'.xls"',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => self::cell($c), $row));
            }
            fclose($out);
        }, $base.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private static function xlsXml(string $title, array $headers, array $rows): string
    {
        $safe = preg_replace('/[\\\\\/\?\*\[\]]+/', ' ', $title) ?: 'Report';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n"
            .'<Worksheet ss:Name="'.self::xml(mb_substr($safe, 0, 31)).'"><Table>'."\n";

        foreach (array_merge([$headers], $rows) as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $v = self::cell($cell);
                $type = is_numeric($v) && $v !== '' ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="'.$type.'">'.self::xml($v).'</Data></Cell>';
            }
            $xml .= '</Row>'."\n";
        }

        return $xml.'</Table></Worksheet></Workbook>';
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
