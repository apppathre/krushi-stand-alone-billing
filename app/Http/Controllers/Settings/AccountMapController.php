<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AccountMap;
use App\Models\Ledger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountMapController extends Controller
{
    public function edit()
    {
        return view('settings.account-maps', [
            'map' => AccountMap::current(),
            'fields' => AccountMap::FIELDS,
            'accounts' => Ledger::books()->where('is_active', true)->orderBy('name')->get(),
            'parties' => Ledger::where('family', 'party')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(AccountMap::FIELDS) as $col) {
            $rules[$col] = ['nullable', 'integer', 'exists:ledgers,id'];
        }
        AccountMap::current()->update($request->validate($rules));

        return back()->with('status', __('Account map saved.'));
    }
}
