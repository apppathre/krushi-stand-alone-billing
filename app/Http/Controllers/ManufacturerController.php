<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;

class ManufacturerController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => Manufacturer::class,
            'route' => 'manufacturers',
            'menu' => 'manufacturers',
            'title' => 'Manufacturers',
            'singular' => 'Manufacturer',
            'search' => ['name', 'name_mr', 'short_code', 'phone'],
            'columns' => [
                ['name' => 'short_code', 'label' => 'Short code'],
                ['name' => 'display_name', 'label' => 'Name'],
                ['name' => 'phone', 'label' => 'Phone'],
                ['name' => 'address', 'label' => 'Address'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'short_code', 'label' => 'Short code', 'hint' => 'Used in bulk item import'],
                ['name' => 'phone', 'label' => 'Phone'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'address', 'label' => 'Address'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'name' => ['required', 'string', 'max:255'],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'short_code' => ['nullable', 'string', 'max:20', 'unique:manufacturers,short_code'.($id ? ','.$id : '')],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }
}
