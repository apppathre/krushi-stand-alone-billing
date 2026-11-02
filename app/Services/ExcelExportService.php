<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Support\ExportCatalog;
use Illuminate\Support\Carbon;

/**
 * Excel-compatible SpreadsheetML (.xls) — opens in Excel / LibreOffice without extra packages.
 */
class ExcelExportService
{
    /**
     * @param  list<string>  $includeKeys
     */
    public function exportXls(string $from, string $to, array $includeKeys): string
    {
        $company = CompanyProfile::current()->display_name ?? config('app.name');
        $sheets = '';

        if (in_array('ledgers', $includeKeys, true)) {
            $sheets .= $this->sheet('Ledgers', [
                ['Code/ID', 'Name', 'Family', 'Type', 'Phone', 'Opening', 'Op side'],
            ], Ledger::query()->where('is_active', true)->orderBy('family')->orderBy('name')->get()
                ->map(fn (Ledger $l) => [
                    (string) $l->id,
                    $l->display_name,
                    $l->family,
                    $l->type,
                    (string) ($l->phone ?? ''),
                    number_format((float) $l->opening_balance, 2, '.', ''),
                    (string) ($l->opening_type ?? 'dr'),
                ])->all());
        }

        $types = ExportCatalog::voucherTypesFor($includeKeys);
        if ($types !== []) {
            $entries = LedgerEntry::with('ledger')
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to)
                ->whereIn('voucher_type', $types)
                ->orderBy('entry_date')->orderBy('id')
                ->get();

            $voucherRows = [];
            foreach ($entries->groupBy(fn ($e) => $e->voucher_type.'|'.$e->voucher_id) as $lines) {
                $first = $lines->first();
                $voucherRows[] = [
                    Carbon::parse($first->entry_date)->toDateString(),
                    (string) $first->voucher_type,
                    (string) ($first->voucher_no ?: $first->voucher_id),
                    number_format((float) $lines->sum('debit'), 2, '.', ''),
                    number_format((float) $lines->sum('credit'), 2, '.', ''),
                    (string) $lines->pluck('narration')->filter()->unique()->implode(' | '),
                ];
            }

            $sheets .= $this->sheet('Vouchers', [
                ['Date', 'Type', 'Voucher no', 'Debit', 'Credit', 'Narration'],
            ], $voucherRows);

            $entryRows = $entries->map(fn (LedgerEntry $e) => [
                Carbon::parse($e->entry_date)->toDateString(),
                (string) $e->voucher_type,
                (string) ($e->voucher_no ?: $e->voucher_id),
                (string) ($e->ledger?->display_name ?? $e->ledger_id),
                number_format((float) $e->debit, 2, '.', ''),
                number_format((float) $e->credit, 2, '.', ''),
                (string) ($e->narration ?? ''),
            ])->all();

            $sheets .= $this->sheet('Ledger entries', [
                ['Date', 'Voucher type', 'Voucher no', 'Ledger', 'Debit', 'Credit', 'Narration'],
            ], $entryRows);
        }

        if (in_array('sales', $includeKeys, true)) {
            $sales = Sale::with('client')->where('status', 'active')
                ->whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
                ->orderBy('invoice_date')->get();
            $sheets .= $this->sheet('Sales', [
                ['Invoice', 'Date', 'Customer', 'Taxable', 'GST', 'Grand total', 'Paid', 'Balance'],
            ], $sales->map(fn (Sale $s) => [
                $s->invoice_no,
                optional($s->invoice_date)->toDateString(),
                $s->customer_name ?: ($s->client?->display_name ?? ''),
                number_format((float) $s->taxable_total, 2, '.', ''),
                number_format((float) $s->cgst_total + (float) $s->sgst_total + (float) $s->igst_total, 2, '.', ''),
                number_format((float) $s->grand_total, 2, '.', ''),
                number_format((float) $s->paid_amount, 2, '.', ''),
                number_format((float) $s->balance, 2, '.', ''),
            ])->all());
        }

        if (in_array('purchases', $includeKeys, true)) {
            $purchases = Purchase::with('supplier')->where('status', 'active')
                ->whereDate('purchase_date', '>=', $from)->whereDate('purchase_date', '<=', $to)
                ->orderBy('purchase_date')->get();
            $sheets .= $this->sheet('Purchases', [
                ['Bill no', 'Date', 'Supplier', 'Taxable', 'GST', 'Grand total', 'Paid', 'Balance'],
            ], $purchases->map(fn (Purchase $p) => [
                $p->purchase_no,
                optional($p->purchase_date)->toDateString(),
                $p->supplier?->display_name ?? '',
                number_format((float) $p->taxable_total, 2, '.', ''),
                number_format((float) $p->cgst_total + (float) $p->sgst_total + (float) $p->igst_total, 2, '.', ''),
                number_format((float) $p->grand_total, 2, '.', ''),
                number_format((float) $p->paid_amount, 2, '.', ''),
                number_format((float) $p->balance, 2, '.', ''),
            ])->all());
        }

        if ($sheets === '') {
            $sheets = $this->sheet('Empty', [['Note']], [['Nothing selected for export']]);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'."\n"
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'."\n"
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'."\n"
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'."\n"
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'."\n"
            .'<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
            .'<Title>'.$this->xml($company).' export</Title>'
            .'<Created>'.now()->toIso8601String().'</Created>'
            .'</DocumentProperties>'."\n"
            .$sheets
            .'</Workbook>'."\n";
    }

    public function suggestedFilename(string $from, string $to): string
    {
        return 'krushi-export-'.$from.'-'.$to.'.xls';
    }

    /** @param  list<list<string>>  $header */
    /** @param  list<list<string>>  $rows */
    private function sheet(string $name, array $header, array $rows): string
    {
        $safe = preg_replace('/[\\\\\/\?\*\[\]]+/', ' ', $name) ?: 'Sheet';
        $xml = '<Worksheet ss:Name="'.$this->xml(mb_substr($safe, 0, 31)).'">'."\n".'<Table>'."\n";
        foreach (array_merge($header, $rows) as $i => $row) {
            $xml .= '<Row>'."\n";
            foreach ($row as $cell) {
                $type = is_numeric($cell) && $cell !== '' ? 'Number' : 'String';
                $xml .= '<Cell><Data ss:Type="'.$type.'">'.$this->xml((string) $cell).'</Data></Cell>'."\n";
            }
            $xml .= '</Row>'."\n";
        }
        $xml .= '</Table>'."\n".'</Worksheet>'."\n";

        return $xml;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
