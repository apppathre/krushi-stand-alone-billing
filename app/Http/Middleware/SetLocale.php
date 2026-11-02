<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const LOCALES = ['en', 'mr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? config('app.locale');

        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
