<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, SetLocale::LOCALES, true), 404);

        $request->session()->put('locale', $locale);
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
