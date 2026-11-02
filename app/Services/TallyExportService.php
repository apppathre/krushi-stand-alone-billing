<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Ledger;
use App\Models\LedgerEntry;
use App\Support\ExportCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Build Tally-compatible XML for Import Data (works for TallyPrime and Tally.ERP 9).
 * Import path: Gateway of Tally → Import → Masters / Transactions (XML).
 */
class TallyExportService
{
    /** Our voucher_type → Tally VCHTYPE name. */
    private const VCH_MAP = [
        'sale' => 'Sales',
        'sale_receipt' => 'Receipt',
        'sale_return' => 'Credit Note',
        'sale_commission' => 'Journal',
        'purchase' => 'Purchase',
        'purchase_payment' => 'Payment',
        'purchase_return' => 'Debit Note',
        'payment_voucher' => 'Payment',
        'receipt_voucher' => 'Receipt',
        'journal_voucher' => 'Journal',
        'contra_voucher' => 'Contra',
    ];

    /** Ledger type/family → Tally parent group. */
    private const GROUP_MAP = [
        'cash' => 'Cash-in-Hand',
        'bank' => 'Bank Accounts',
        'client' => 'Sundry Debtors',
        'both' => 'Sundry Debtors',
        'supplier' => 'Sundry Creditors',
        'sales' => 'Sales Accounts',
        'purchase' => 'Purchase Accounts',
        'expense' => 'Indirect Expenses',
        'income' => 'Indirect Incomes',
        'tax' => 'Duties & Taxes',
        'discount' => 'Indirect Expenses',
        'round_off' => 'Indirect Expenses',
        'capital' => 'Capital Account',
        'other' => 'Suspense A/c',
    ];

    /**
     * @param  'prime'|'erp9'  $edition
     * @param  'masters'|'vouchers'|'both'  $scope
     * @param  list<string>|null  $includeKeys  from ExportCatalog (null = defaults)
     */
    public function exportXml(string $edition, string $scope, string $from, string $to, ?string $companyName = null, ?array $includeKeys = null): string
    {
        $edition = in_array($edition, ['prime', 'erp9'], true) ? $edition : 'prime';
        $scope = in_array($scope, ['masters', 'vouchers', 'both'], true) ? $scope : 'both';
        $includeKeys = $includeKeys ?: ExportCatalog::defaultIncludeKeys();
        $company = $companyName ?: (CompanyProfile::current()->display_name ?? config('app.name'));

        $wantLedgers = in_array('ledgers', $includeKeys, true);
        $voucherTypes = ExportCatalog::voucherTypesFor($includeKeys);

        $messages = '';
        if (($scope === 'masters' || $scope === 'both') && $wantLedgers) {
            $messages .= $this->mastersMessages();
        }
        if (($scope === 'vouchers' || $scope === 'both') && $voucherTypes !== []) {
            $messages .= $this->vouchersMessages($from, $to, $voucherTypes);
        }

        $target = $edition === 'erp9' ? 'Tally.ERP9' : 'TallyPrime';
        $id = $scope === 'masters' ? 'All Masters' : ($scope === 'vouchers' ? 'Vouchers' : 'All Masters');
        $report = $scope === 'vouchers' ? 'Vouchers' : 'All Masters';

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            ."<!-- Krushi → {$target} XML | {$from} to {$to} | Import: Gateway → Import Data -->\n"
            .'<ENVELOPE>'."\n"
            .'<HEADER>'."\n"
            .'<VERSION>1</VERSION>'."\n"
            .'<TALLYREQUEST>Import Data</TALLYREQUEST>'."\n"
            .'<TYPE>Data</TYPE>'."\n"
            .'<ID>'.$this->xml($id).'</ID>'."\n"
            .'</HEADER>'."\n"
            .'<BODY>'."\n"
            .'<IMPORTDATA>'."\n"
            .'<REQUESTDESC>'."\n"
            .'<REPORTNAME>'.$this->xml($report).'</REPORTNAME>'."\n"
            .'<STATICVARIABLES>'."\n"
            .'<SVCURRENTCOMPANY>'.$this->xml($company).'</SVCURRENTCOMPANY>'."\n"
            .'</STATICVARIABLES>'."\n"
            .'</REQUESTDESC>'."\n"
            .'<REQUESTDATA>'."\n"
            .$messages
            .'</REQUESTDATA>'."\n"
            .'</IMPORTDATA>'."\n"
            .'</BODY>'."\n"
            .'</ENVELOPE>'."\n";
    }

    public function suggestedFilename(string $edition, string $scope, string $from, string $to): string
    {
        $ed = $edition === 'erp9' ? 'erp9' : 'prime';

        return 'tally-'.$ed.'-'.$scope.'-'.$from.'-'.$to.'.xml';
    }

    private function mastersMessages(): string
    {
        $groups = collect(array_unique(array_values(self::GROUP_MAP)));
        $xml = '';

        // Ensure parent groups exist (safe Create if missing in Tally).
        foreach ($groups as $group) {
            $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n"
                .'<GROUP NAME="'.$this->xml($group).'" ACTION="Create">'."\n"
                .'<NAME.LIST><NAME>'.$this->xml($group).'</NAME></NAME.LIST>'."\n"
                .'<PARENT/>'."\n"
                .'<ISSUBLEDGER>No</ISSUBLEDGER>'."\n"
                .'</GROUP>'."\n"
                .'</TALLYMESSAGE>'."\n";
        }

        $ledgers = Ledger::query()->where('is_active', true)->orderBy('family')->orderBy('name')->get();
        foreach ($ledgers as $ledger) {
            $name = $ledger->display_name;
            $parent = $this->parentGroup($ledger);
            $opening = round(abs((float) $ledger->opening_balance), 2);
            $isDr = ($ledger->opening_type ?? 'dr') !== 'cr';

            $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n"
                .'<LEDGER NAME="'.$this->xml($name).'" ACTION="Create">'."\n"
                .'<NAME.LIST><NAME>'.$this->xml($name).'</NAME></NAME.LIST>'."\n"
                .'<PARENT>'.$this->xml($parent).'</PARENT>'."\n"
                .'<OPENINGBALANCE>'.($isDr ? '' : '-').number_format($opening, 2, '.', '').'</OPENINGBALANCE>'."\n"
                .'</LEDGER>'."\n"
                .'</TALLYMESSAGE>'."\n";
        }

        return $xml;
    }

    /** @param  list<string>  $voucherTypes */
    private function vouchersMessages(string $from, string $to, array $voucherTypes): string
    {
        $entries = LedgerEntry::with('ledger')
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->whereIn('voucher_type', $voucherTypes)
            ->orderBy('entry_date')
            ->orderBy('voucher_type')
            ->orderBy('voucher_id')
            ->orderBy('id')
            ->get();

        /** @var Collection<string, Collection<int, LedgerEntry>> $groups */
        $groups = $entries->groupBy(fn (LedgerEntry $e) => $e->voucher_type.'|'.$e->voucher_id);
        $xml = '';

        foreach ($groups as $key => $lines) {
            /** @var Collection<int, LedgerEntry> $lines */
            $first = $lines->first();
            if (! $first) {
                continue;
            }
            $vchType = self::VCH_MAP[$first->voucher_type] ?? 'Journal';
            $date = Carbon::parse($first->entry_date)->format('Ymd');
            $voucherNo = $first->voucher_no ?: ($first->voucher_type.'-'.$first->voucher_id);
            $narration = $lines->pluck('narration')->filter()->unique()->implode(' · ');
            $narration = $narration !== '' ? $narration : $vchType.' '.$voucherNo;

            $dr = round((float) $lines->sum('debit'), 2);
            $cr = round((float) $lines->sum('credit'), 2);
            if (abs($dr - $cr) > 0.05) {
                continue; // skip unbalanced (should not happen)
            }

            $partyName = $this->guessPartyName($lines, $vchType);

            $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n"
                .'<VOUCHER VCHTYPE="'.$this->xml($vchType).'" ACTION="Create" OBJVIEW="Accounting Voucher View">'."\n"
                .'<DATE>'.$date.'</DATE>'."\n"
                .'<EFFECTIVEDATE>'.$date.'</EFFECTIVEDATE>'."\n"
                .'<VOUCHERTYPENAME>'.$this->xml($vchType).'</VOUCHERTYPENAME>'."\n"
                .'<VOUCHERNUMBER>'.$this->xml($voucherNo).'</VOUCHERNUMBER>'."\n"
                .'<REFERENCE>'.$this->xml($voucherNo).'</REFERENCE>'."\n"
                .'<NARRATION>'.$this->xml($narration).'</NARRATION>'."\n"
                .'<PERSISTEDVIEW>Accounting Voucher View</PERSISTEDVIEW>'."\n";

            if ($partyName) {
                $xml .= '<PARTYLEDGERNAME>'.$this->xml($partyName).'</PARTYLEDGERNAME>'."\n";
            }

            foreach ($lines as $line) {
                $ledgerName = $line->ledger?->display_name ?: ('Ledger#'.$line->ledger_id);
                $debit = round((float) $line->debit, 2);
                $credit = round((float) $line->credit, 2);
                if ($debit < 0.005 && $credit < 0.005) {
                    continue;
                }

                // Tally convention: Dr → ISDEEMEDPOSITIVE Yes + negative amount; Cr → No + positive.
                if ($debit >= 0.005) {
                    $isDeemed = 'Yes';
                    $amount = number_format(-$debit, 2, '.', '');
                } else {
                    $isDeemed = 'No';
                    $amount = number_format($credit, 2, '.', '');
                }

                $xml .= '<ALLLEDGERENTRIES.LIST>'."\n"
                    .'<LEDGERNAME>'.$this->xml($ledgerName).'</LEDGERNAME>'."\n"
                    .'<ISDEEMEDPOSITIVE>'.$isDeemed.'</ISDEEMEDPOSITIVE>'."\n"
                    .'<AMOUNT>'.$amount.'</AMOUNT>'."\n"
                    .'</ALLLEDGERENTRIES.LIST>'."\n";
            }

            $xml .= '</VOUCHER>'."\n"
                .'</TALLYMESSAGE>'."\n";
        }

        return $xml;
    }

    private function parentGroup(Ledger $ledger): string
    {
        if ($ledger->family === 'party') {
            return self::GROUP_MAP[$ledger->type] ?? 'Sundry Debtors';
        }

        return self::GROUP_MAP[$ledger->type] ?? 'Suspense A/c';
    }

    /** @param  Collection<int, LedgerEntry>  $lines */
    private function guessPartyName(Collection $lines, string $vchType): ?string
    {
        $party = $lines->first(fn (LedgerEntry $e) => $e->ledger && $e->ledger->family === 'party');
        if ($party?->ledger) {
            return $party->ledger->display_name;
        }

        // Contra / cash: pick cash-bank leg as "party" hint for Tally UI
        if (in_array($vchType, ['Contra', 'Receipt', 'Payment'], true)) {
            $cash = $lines->first(fn (LedgerEntry $e) => $e->ledger && in_array($e->ledger->type, ['cash', 'bank'], true));

            return $cash?->ledger?->display_name;
        }

        return null;
    }

    private function xml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
