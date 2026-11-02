<?php

namespace App\Http\Controllers;

use App\Models\HsnCode;

class HsnCodeController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => HsnCode::class,
            'route' => 'hsn-codes',
            'menu' => 'hsn_codes',
            'title' => 'HSN / SAC',
            'singular' => 'HSN / SAC',
            'order' => 'code',
            'search' => ['code', 'name', 'name_mr'],
            'columns' => [
                ['name' => 'code', 'label' => 'Code'],
                ['name' => 'kind', 'label' => 'Kind'],
                ['name' => 'display_name', 'label' => 'Name'],
                ['name' => 'gst_rate', 'label' => 'Default GST %'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Code', 'required' => true],
                ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'required' => true, 'options' => ['hsn' => 'HSN', 'sac' => 'SAC']],
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'gst_rate', 'label' => 'Default GST %', 'type' => 'number', 'required' => true],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'code' => ['required', 'string', 'max:12', 'unique:hsn_codes,code'.($id ? ','.$id : '')],
                'kind' => ['required', 'in:hsn,sac'],
                'name' => ['required', 'string', 'max:255'],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'gst_rate' => ['required', 'numeric', 'between:0,100'],
            ],
        ];
    }
}
