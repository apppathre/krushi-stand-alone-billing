<?php

namespace App\Http\Controllers;

use App\Models\Category;

class CategoryController extends MasterController
{
    protected function config(): array
    {
        return [
            'model' => Category::class,
            'route' => 'categories',
            'menu' => 'categories',
            'title' => 'Categories',
            'singular' => 'Category',
            'search' => ['name', 'name_mr'],
            'columns' => [['name' => 'display_name', 'label' => 'Name'], ['name' => 'description', 'label' => 'Description']],
            'fields' => [
                ['name' => 'name', 'label' => 'Name (English)', 'required' => true],
                ['name' => 'name_mr', 'label' => 'Name (Marathi)'],
                ['name' => 'description', 'label' => 'Description'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'rules' => fn ($id) => [
                'name' => ['required', 'string', 'max:255', 'unique:categories,name'.($id ? ','.$id : '')],
                'name_mr' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }
}
