<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Services\PartyStatementService;
use App\Support\TermsHtml;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClientStatementController extends Controller
{
    /** Public encrypted-token portal: client sees dues, open bills, pay info, terms. */
    public function show(string $token, PartyStatementService $svc): View|Response
    {
        $row = $svc->findValid($token);
        if (! $row || ! $row->ledger) {
            return response()->view('client-statement.expired', [], 410);
        }

        $svc->touch($row);
        $company = CompanyProfile::current();
        $party = $row->ledger;
        $data = $svc->statement($party, (bool) $row->include_bills);

        return view('client-statement.show', [
            'company' => $company,
            'party' => $party,
            'token' => $row,
            'amount' => $data['amount'],
            'side' => $data['side'],
            'label' => $data['label'],
            'bills' => $data['bills'],
            'allowPayment' => (bool) $row->allow_payment,
            'termsHtml' => TermsHtml::fromCompany($company->invoice_terms),
        ]);
    }
}
