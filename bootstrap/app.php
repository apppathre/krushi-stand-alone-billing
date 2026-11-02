<?php

use App\Http\Middleware\EnsureMenuAccess;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ShareShellData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
            ShareShellData::class,
        ]);

        $middleware->alias([
            'menu' => EnsureMenuAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
