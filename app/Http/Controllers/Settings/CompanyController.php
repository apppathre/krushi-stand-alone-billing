<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Support\IndianStates;
use App\Support\TermsHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function edit()
    {
        return view('settings.company', ['company' => CompanyProfile::current(), 'states' => IndianStates::options()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_mr' => ['nullable', 'string', 'max:255'],
            'short_code' => ['nullable', 'string', 'max:20'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_code' => ['required', Rule::in(array_keys(IndianStates::LIST))],
            'pincode' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'gstin' => ['nullable', 'string', 'size:15'],
            'pan' => ['nullable', 'string', 'size:10'],
            'seed_licence_no' => ['nullable', 'string', 'max:100'],
            'seed_licence_valid_till' => ['nullable', 'date'],
            'fertilizer_licence_no' => ['nullable', 'string', 'max:100'],
            'fertilizer_licence_valid_till' => ['nullable', 'date'],
            'pesticide_licence_no' => ['nullable', 'string', 'max:100'],
            'pesticide_licence_valid_till' => ['nullable', 'date'],
            'shop_act_licence_no' => ['nullable', 'string', 'max:120'],
            'shop_act_valid_till' => ['nullable', 'date'],
            'fssai_licence_no' => ['nullable', 'string', 'max:120'],
            'fssai_valid_till' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'upi_id' => ['nullable', 'string', 'max:100'],
            'default_invoice_print_format' => ['nullable', 'string', Rule::in(array_keys(\App\Support\PrintFormats::forSale()))],
            'invoice_print_lang' => ['nullable', 'in:auto,en,mr'],
            'invoice_terms' => ['nullable', 'string', 'max:20000'],
            'outstanding_wa_template' => ['nullable', 'string', 'max:4000'],
            'statement_link_enabled' => ['sometimes', 'boolean'],
            'statement_allow_payment' => ['sometimes', 'boolean'],
            'statement_link_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
        $data['state_name'] = IndianStates::name($data['state_code']);
        $data['short_code'] = filled($data['short_code'] ?? null) ? strtoupper(trim($data['short_code'])) : null;
        $data['gstin'] = isset($data['gstin']) ? strtoupper($data['gstin']) : null;
        $data['pan'] = isset($data['pan']) ? strtoupper($data['pan']) : null;
        $data['default_invoice_print_format'] = $data['default_invoice_print_format'] ?? 'standard';
        $data['invoice_print_lang'] = $data['invoice_print_lang'] ?? 'auto';
        $data['invoice_terms'] = TermsHtml::sanitize($data['invoice_terms'] ?? null);
        $data['statement_link_enabled'] = $request->boolean('statement_link_enabled');
        $data['statement_allow_payment'] = $request->boolean('statement_allow_payment');
        $data['statement_link_days'] = (int) ($data['statement_link_days'] ?? 7);

        CompanyProfile::current()->update($data);
        CompanyProfile::forgetCurrent();

        return back()->with('status', __('Company profile saved.'));
    }
}
