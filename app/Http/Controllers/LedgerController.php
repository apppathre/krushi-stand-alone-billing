<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Area;
use App\Models\Ledger;
use App\Models\User;
use App\Support\IndianStates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One controller for the three ledger screens: clients, suppliers and books.
 * The kind is the first segment of the route name (clients.index, books.edit, ...).
 */
class LedgerController extends Controller
{
    use RespondsForDrawer;

    private function kinds(): array
    {
        return [
            'clients' => [
                'title' => 'Clients', 'singular' => 'Client', 'family' => 'party', 'scope' => 'clients',
                'types' => ['client' => 'Client', 'both' => 'Client & Supplier'],
            ],
            'suppliers' => [
                'title' => 'Suppliers', 'singular' => 'Supplier', 'family' => 'party', 'scope' => 'suppliers',
                'types' => ['supplier' => 'Supplier', 'both' => 'Client & Supplier'],
            ],
            'books' => [
                'title' => 'Ledger Master', 'singular' => 'Account', 'family' => 'account', 'scope' => 'books',
                'types' => collect(Ledger::ACCOUNT_TYPES)->mapWithKeys(fn ($t) => [$t => ucwords(str_replace('_', ' ', $t))])->all(),
            ],
        ];
    }

    private function kind(Request $request): array
    {
        $key = explode('.', (string) $request->route()->getName())[0];

        return ['key' => $key] + $this->kinds()[$key];
    }

    public function index(Request $request)
    {
        $k = $this->kind($request);

        $query = Ledger::query()->{$k['scope']}()
            ->with(['area', 'salesperson'])
            ->withSum('entries as dr_sum', 'debit')->withSum('entries as cr_sum', 'credit')
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('name_mr', 'like', $t)
                    ->orWhere('phone', 'like', $t)->orWhere('gstin', 'like', $t)->orWhere('village', 'like', $t));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('area_id'), fn ($q) => $q->where('area_id', $request->area_id))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));

        // Account books: group by ledger type (Tally-style) in one table — no GST rate master here.
        if ($k['key'] === 'books') {
            $rows = $query->orderBy('type')->orderBy('name')->get();
            $groups = $rows->groupBy('type');

            return view('ledgers.index', compact('k', 'rows', 'groups'));
        }

        $rows = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('ledgers.index', [
            'k' => $k,
            'rows' => $rows,
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $k = $this->kind($request);
        $company = \App\Models\CompanyProfile::current();
        $openingType = $k['key'] === 'suppliers'
            ? (string) ($company->default_supplier_opening_type ?: 'cr')
            : (string) ($company->default_client_opening_type ?: 'dr');
        if (! in_array($openingType, ['dr', 'cr'], true)) {
            $openingType = $k['key'] === 'suppliers' ? 'cr' : 'dr';
        }

        $defaults = [
            'is_active' => true,
            'opening_type' => $openingType,
            'opening_balance' => 0,
            'type' => array_key_first($k['types']),
        ];
        if ($k['family'] === 'party') {
            $defaults['credit_days'] = (int) ($company->default_credit_days ?? 30);
            $defaults['credit_limit'] = (float) ($company->default_credit_limit ?? 0);
            if ((float) ($company->default_commission_pct ?? 0) > 0) {
                $defaults['commission_pct'] = (float) $company->default_commission_pct;
            }
        }

        return view('ledgers._drawer', $this->formExtras($k) + [
            'k' => $k,
            'ledger' => new Ledger($defaults),
            'forSelect' => $request->boolean('select') || $request->header('X-Party-Select') === '1',
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $k = $this->kind($request);
        $ledger = Ledger::create($this->payload($request, $k));

        // Sale/purchase desks: return party for select without full page reload.
        if ($this->wantsJsonResponse($request) && ($request->boolean('select') || $request->header('X-Party-Select') === '1')) {
            return response()->json([
                'ok' => true,
                'message' => __(':item saved.', ['item' => __($k['singular'])]),
                'reload' => false,
                'party' => [
                    'id' => $ledger->id,
                    'name' => $ledger->display_name,
                    'phone' => $ledger->phone,
                    'state_code' => $ledger->state_code,
                    'credit_days' => $ledger->credit_days,
                    'kind' => $k['key'],
                ],
            ]);
        }

        return $this->savedResponse($request, __(':item saved.', ['item' => __($k['singular'])]), $k['key'].'.index');
    }

    public function edit(Request $request, Ledger $ledger)
    {
        $k = $this->kind($request);
        $this->ensureKind($ledger, $k);

        return view('ledgers._drawer', $this->formExtras($k) + ['k' => $k, 'ledger' => $ledger]);
    }

    public function update(Request $request, Ledger $ledger): JsonResponse|RedirectResponse
    {
        $k = $this->kind($request);
        $this->ensureKind($ledger, $k);
        $data = $this->payload($request, $k);
        if ($ledger->is_system) {
            unset($data['type']); // system ledgers keep their type
        }
        $ledger->update($data);

        return $this->savedResponse($request, __(':item saved.', ['item' => __($k['singular'])]), $k['key'].'.index');
    }

    public function destroy(Request $request, Ledger $ledger): JsonResponse|RedirectResponse
    {
        $k = $this->kind($request);
        $this->ensureKind($ledger, $k);

        if ($ledger->is_system || $ledger->entries()->exists()) {
            return $this->failedResponse($request, __('This ledger has transactions or is a system ledger. Deactivate it instead.'));
        }
        try {
            $ledger->delete();
        } catch (\Illuminate\Database\QueryException) {
            return $this->failedResponse($request, __('This record is in use and cannot be deleted. Deactivate it instead.'));
        }

        return $this->savedResponse($request, __(':item deleted.', ['item' => __($k['singular'])]), $k['key'].'.index');
    }

    private function ensureKind(Ledger $ledger, array $k): void
    {
        abort_unless($ledger->family === $k['family'], 404);
    }

    private function formExtras(array $k): array
    {
        return [
            'states' => IndianStates::options(),
            'areas' => $k['family'] === 'party'
                ? Area::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'salespeople' => $k['family'] === 'party'
                ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }

    private function payload(Request $request, array $k): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'name_mr' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys($k['types']))],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'village' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:150'],
            'state_code' => ['nullable', Rule::in(array_keys(IndianStates::LIST))],
            'gstin' => ['nullable', 'string', 'size:15'],
            'pan' => ['nullable', 'string', 'size:10'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_type' => ['required', 'in:dr,cr'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'salesperson_user_id' => ['nullable', 'exists:users,id'],
            'commission_pct' => ['nullable', 'numeric', 'between:0,100'],
        ];

        $data = $request->validate($rules);

        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');
        $data['family'] = $k['family'];
        $data['gstin'] = filled($data['gstin'] ?? null) ? strtoupper(trim($data['gstin'])) : null;
        $data['pan'] = filled($data['pan'] ?? null) ? strtoupper(trim($data['pan'])) : null;
        if ($k['family'] !== 'party') {
            unset($data['credit_limit'], $data['credit_days'], $data['area_id'], $data['salesperson_user_id'], $data['commission_pct'], $data['village'], $data['city'], $data['gstin'], $data['pan']);
        } else {
            $data['area_id'] = $data['area_id'] ?: null;
            $data['salesperson_user_id'] = $data['salesperson_user_id'] ?: null;
            $data['credit_days'] = $data['credit_days'] !== null && $data['credit_days'] !== '' ? (int) $data['credit_days'] : null;
            $data['commission_pct'] = filled($data['commission_pct'] ?? null) ? $data['commission_pct'] : null;
        }

        return $data;
    }
}
