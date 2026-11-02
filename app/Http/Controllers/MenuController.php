<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForDrawer;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use RespondsForDrawer;

    /** Menus that must stay active so the owner can never lock themselves out. */
    private const PROTECTED = ['dashboard', 'menus', 'roles', 'users'];

    public function index()
    {
        return view('menus.index', [
            'menus' => Menu::whereNull('parent_id')->with('children.children')->orderBy('sort_order')->get(),
            'protected' => self::PROTECTED,
        ]);
    }

    public function edit(Menu $menu)
    {
        return view('menus._drawer', ['menu' => $menu, 'protected' => in_array($menu->key, self::PROTECTED, true)]);
    }

    public function update(Request $request, Menu $menu): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'label_en' => ['required', 'string', 'max:100'],
            'label_mr' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['is_active'] = in_array($menu->key, self::PROTECTED, true) ? true : $request->boolean('is_active');
        $menu->update($data);
        User::forgetMenuCache();

        return $this->savedResponse($request, __('Menu saved.'), 'menus.index');
    }
}
