<?php

namespace App\Http\Controllers;

use App\Models\Unit;

class UnitController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => Unit::class,
            'route' => 'units',
            'menu' => 'units',
            'title' => 'Units',
            'singular' => 'Unit',
            'search' => ['name', 'name_mr', 'short_name'],
            'columns' => [['name' => 'display_name', 'label' => 'Name'], ['name' => 'short_name', 'label' => 'Short name']],
            'fields' => [
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'short_name', 'label' => 'Short name', 'required' => true],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => [
                'name' => ['required', 'string', 'max:100'],
                'name_mr' => ['nullable', 'string', 'max:100'],
                'short_name' => ['required', 'string', 'max:10'],
            ],
        ];
    }
}
