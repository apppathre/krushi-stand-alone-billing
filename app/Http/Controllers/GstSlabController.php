<?php

namespace App\Http\Controllers;

use App\Models\GstSlab;

class GstSlabController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => GstSlab::class,
            'route' => 'gst-slabs',
            'menu' => 'gst_slabs',
            'title' => 'GST Slabs',
            'singular' => 'GST slab',
            'order' => 'sort_order',
            'search' => ['name', 'name_mr', 'rate'],
            'columns' => [
                ['name' => 'rate', 'label' => 'Rate %'],
                ['name' => 'display_name', 'label' => 'Name'],
                ['name' => 'sort_order', 'label' => 'Sort'],
            ],
            'fields' => [
                ['name' => 'rate', 'label' => 'GST rate %', 'type' => 'number', 'required' => true],
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'rate' => ['required', 'numeric', 'between:0,100', 'unique:gst_slabs,rate'.($id ? ','.$id : '')],
                'name' => ['required', 'string', 'max:255'],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
        ];
    }
}
