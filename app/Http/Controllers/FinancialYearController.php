<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\FinancialYear;
use App\Services\FinancialYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FinancialYearController extends Controller
{
    use RespondsForDrawer;

    public function __construct(private FinancialYearService $years) {}

    public function index()
    {
        return view('financial-years.index', [
            'years' => FinancialYear::withCount('documentSeries')->orderByDesc('start_date')->get(),
            'current' => FinancialYear::current(),
            'suggest' => $this->years->suggest(),
        ]);
    }

    public function create()
    {
        $suggest = $this->years->suggest();

        return view('financial-years._drawer', [
            'year' => new FinancialYear($suggest + ['is_current' => false]),
            'suggest' => $suggest,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $this->years->create($data, $request->boolean('make_current'), true);
        } catch (ValidationException $e) {
            return $this->failedResponse($request, collect($e->errors())->flatten()->first() ?: __('Could not save.'));
        }

        return $this->savedResponse($request, __('Financial year saved.'), 'financial-years.index');
    }

    public function edit(FinancialYear $financialYear)
    {
        return view('financial-years._drawer', [
            'year' => $financialYear,
            'suggest' => $this->years->suggest($financialYear),
        ]);
    }

    public function update(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, $financialYear);
        try {
            $this->years->update($financialYear, $data);
            if ($request->boolean('make_current')) {
                $this->years->activate($financialYear);
            }
        } catch (ValidationException $e) {
            return $this->failedResponse($request, collect($e->errors())->flatten()->first() ?: __('Could not save.'));
        }

        return $this->savedResponse($request, __('Financial year saved.'), 'financial-years.index');
    }

    public function destroy(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        try {
            $this->years->delete($financialYear);
        } catch (ValidationException $e) {
            return $this->failedResponse($request, collect($e->errors())->flatten()->first() ?: __('Could not delete.'));
        }

        return $this->savedResponse($request, __('Financial year deleted.'), 'financial-years.index');
    }

    public function activate(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $this->years->activate($financialYear);

        return $this->savedResponse($request, __('Active financial year set to :name.', ['name' => $financialYear->name]), 'financial-years.index');
    }

    public function createNext(Request $request): JsonResponse|RedirectResponse
    {
        $from = FinancialYear::orderByDesc('end_date')->first() ?? FinancialYear::current();
        try {
            $this->years->createNext($from, $request->boolean('activate'));
        } catch (ValidationException $e) {
            return $this->failedResponse($request, collect($e->errors())->flatten()->first() ?: __('Could not create next year.'));
        }

        return $this->savedResponse($request, __('Next financial year created.'), 'financial-years.index');
    }

    private function validated(Request $request, ?FinancialYear $year = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:financial_years,name'.($year ? ','.$year->id : '')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }
}
