<?php

namespace App\Http\Controllers;

use App\Models\Area;

/** Area master — farm / recovery zones for clients (and suppliers). */
class AreaController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => Area::class,
            'route' => 'areas',
            'menu' => 'areas',
            'title' => 'Areas',
            'singular' => 'Area',
            'search' => ['name', 'name_mr', 'short_code', 'description'],
            'columns' => [
                ['name' => 'short_code', 'label' => 'Short code'],
                ['name' => 'display_name', 'label' => 'Name'],
                ['name' => 'description', 'label' => 'Description'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'short_code', 'label' => 'Short code', 'hint' => 'e.g. WDG, SHR for recovery lists'],
                ['name' => 'description', 'label' => 'Description'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'name' => ['required', 'string', 'max:150'],
                'name_mr' => ['nullable', 'string', 'max:150'],
                'short_code' => ['nullable', 'string', 'max:20', 'unique:areas,short_code'.($id ? ','.$id : '')],
                'description' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }
}
