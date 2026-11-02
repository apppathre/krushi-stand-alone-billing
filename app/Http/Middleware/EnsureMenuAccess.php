<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('menu:products') — the action (view/add/edit/delete) is derived
 * from the route name suffix; pass it explicitly as 'menu:products,delete' to override.
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuKey, ?string $action = null): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $action ??= $this->actionFor($request);

        abort_unless(
            $user->canMenu($menuKey, $action),
            403,
            __('You do not have permission to :action this section.', ['action' => __($action)])
        );

        return $next($request);
    }

    private function actionFor(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        $suffix = substr($name, (int) strrpos($name, '.') + 1);

        return match ($suffix) {
            'create', 'store' => 'add',
            'edit', 'update' => 'edit',
            'destroy', 'cancel' => 'delete',
            default => 'view',
        };
    }
}
