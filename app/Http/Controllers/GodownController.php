<?php

namespace App\Http\Controllers;

use App\Models\Godown;

class GodownController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => Godown::class,
            'route' => 'godowns',
            'menu' => 'godowns',
            'title' => 'Godowns',
            'singular' => 'Godown',
            'search' => ['name', 'name_mr', 'code'],
            'columns' => [['name' => 'display_name', 'label' => 'Name'], ['name' => 'code', 'label' => 'Code'], ['name' => 'address', 'label' => 'Address']],
            'fields' => [
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'code', 'label' => 'Code'],
                ['name' => 'address', 'label' => 'Address'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'name' => ['required', 'string', 'max:255'],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'code' => ['nullable', 'string', 'max:20', 'unique:godowns,code'.($id ? ','.$id : '')],
                'address' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }
}
