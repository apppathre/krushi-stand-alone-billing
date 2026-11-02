<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AccountMap;
use App\Models\CompanyProfile;
use App\Models\Ledger;
use App\Support\PrintFormats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner / admin control: party credit defaults, opening Dr/Cr, commission, print prefs, live stock.
 */
class AppControlController extends Controller
{
    public function edit()
    {
        return view('settings.app-control', [
            'company' => CompanyProfile::current(),
            'map' => AccountMap::current(),
            'accounts' => Ledger::where('family', 'account')->where('is_active', true)->orderBy('name')->get(),
            'printFormats' => PrintFormats::saleLabels(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_credit_days' => ['required', 'integer', 'min:0', 'max:365'],
            'overdue_interest_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_credit_limit' => ['required', 'numeric', 'min:0'],
            'default_client_opening_type' => ['required', 'in:dr,cr'],
            'default_supplier_opening_type' => ['required', 'in:dr,cr'],
            'commission_enabled' => ['sometimes', 'boolean'],
            'default_commission_pct' => ['nullable', 'numeric', 'between:0,100'],
            'enforce_credit_limit' => ['sometimes', 'boolean'],
            'default_invoice_print_format' => ['required', 'string', Rule::in(array_keys(PrintFormats::forSale()))],
            'invoice_print_lang' => ['required', 'in:auto,en,mr'],
            'default_print_size' => ['required', 'in:a4,half,quarter'],
            'commission_expense_ledger_id' => ['nullable', 'exists:ledgers,id'],
            'commission_payable_ledger_id' => ['nullable', 'exists:ledgers,id'],
            'live_stock_enabled' => ['sometimes', 'boolean'],
            'live_stock_title' => ['nullable', 'string', 'max:120'],
            'live_stock_passkey' => ['nullable', 'string', 'min:4', 'max:100'],
            'live_stock_refresh_sec' => ['nullable', 'integer', 'min:10', 'max:300'],
            'live_stock_godown_id' => ['nullable', 'exists:godowns,id'],
            'live_stock_show_rate' => ['sometimes', 'boolean'],
            'live_stock_default_view' => ['required', 'in:table,cards'],
            'live_stock_theme' => ['required', 'in:day,night'],
            'live_stock_on_dashboard' => ['sometimes', 'boolean'],
        ]);

        $data['commission_enabled'] = $request->boolean('commission_enabled');
        $data['enforce_credit_limit'] = $request->boolean('enforce_credit_limit');
        $data['live_stock_enabled'] = $request->boolean('live_stock_enabled');
        $data['live_stock_show_rate'] = $request->boolean('live_stock_show_rate');
        $data['live_stock_on_dashboard'] = $request->boolean('live_stock_on_dashboard');
        $data['default_commission_pct'] = (float) ($data['default_commission_pct'] ?? 0);
        $data['default_credit_limit'] = (float) $data['default_credit_limit'];
        $data['overdue_interest_pct'] = (float) ($data['overdue_interest_pct'] ?? 0);
        $data['live_stock_refresh_sec'] = (int) ($data['live_stock_refresh_sec'] ?? 30);
        $data['live_stock_godown_id'] = $data['live_stock_godown_id'] ?: null;

        $company = CompanyProfile::current();
        $update = collect($data)->only([
            'default_credit_days', 'overdue_interest_pct', 'default_credit_limit',
            'default_client_opening_type', 'default_supplier_opening_type',
            'commission_enabled', 'default_commission_pct', 'enforce_credit_limit',
            'default_invoice_print_format', 'invoice_print_lang', 'default_print_size',
            'live_stock_enabled', 'live_stock_title', 'live_stock_refresh_sec',
            'live_stock_godown_id', 'live_stock_show_rate',
            'live_stock_default_view', 'live_stock_theme', 'live_stock_on_dashboard',
        ])->all();

        if (filled($data['live_stock_passkey'] ?? null)) {
            $update['live_stock_passkey'] = Hash::make($data['live_stock_passkey']);
        }

        if ($data['live_stock_enabled'] && blank($update['live_stock_passkey'] ?? null) && blank($company->live_stock_passkey)) {
            throw ValidationException::withMessages([
                'live_stock_passkey' => __('Set a passkey before enabling the live stock screen.'),
            ]);
        }

        $company->update($update);

        AccountMap::current()->update([
            'commission_expense_ledger_id' => $data['commission_expense_ledger_id'] ?: null,
            'commission_payable_ledger_id' => $data['commission_payable_ledger_id'] ?: null,
        ]);

        return back()->with('status', __('App control settings saved.'));
    }
}
