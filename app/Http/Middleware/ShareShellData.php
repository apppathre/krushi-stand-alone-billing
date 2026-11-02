<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Models\FinancialYear;
use App\Services\FinancialYearService;
use App\Support\WorkContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Shares company, godowns, menus and locale with every view (cached / memoized). */
class ShareShellData
{
    private static ?bool $hasCompanyTable = null;

    private static ?bool $hasFyTable = null;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = app()->getLocale();
        View::share('locale', $locale);

        if (! $this->hasCompanyTable()) {
            return $next($request);
        }

        View::share('shellCompany', CompanyProfile::current());

        $user = $request->user();
        if ($user) {
            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => __('Your account is disabled.')]);
            }

            $user->loadMissing('role');

            $godowns = $user->accessibleGodowns();
            $currentId = (int) $request->session()->get('godown_id');
            if (! $godowns->contains('id', $currentId)) {
                $currentId = (int) ($godowns->first()->id ?? 0);
                $request->session()->put('godown_id', $currentId);
            }

            if (! $request->session()->has('work_scope')) {
                $request->session()->put('work_scope', WorkContext::SCOPE_SHOP);
            }

            View::share('shellGodowns', $godowns);
            View::share('currentGodown', $godowns->firstWhere('id', $currentId));
            View::share('workScope', WorkContext::scope($request));

            // Drawer / list AJAX fragments do not render the sidebar — skip menu tree work.
            if (! $this->isFragmentRequest($request)) {
                View::share('shellMenus', $user->shellMenuTree());
            } else {
                View::share('shellMenus', collect());
            }

            if ($this->hasFyTable()) {
                $fy = FinancialYear::current();
                View::share('currentFinancialYear', $fy);
                View::share('voucherDate', app(FinancialYearService::class)->defaultVoucherDate());
            }
        }

        return $next($request);
    }

    private function isFragmentRequest(Request $request): bool
    {
        return $request->headers->get('X-UI-Drawer') === '1'
            || ($request->ajax() && $request->boolean('list'));
    }

    private function hasCompanyTable(): bool
    {
        return self::$hasCompanyTable ??= Schema::hasTable('company_profile');
    }

    private function hasFyTable(): bool
    {
        return self::$hasFyTable ??= Schema::hasTable('financial_years');
    }
}
