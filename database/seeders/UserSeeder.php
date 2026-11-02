<?php

namespace Database\Seeders;

use App\Models\Godown;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $godowns = Godown::pluck('id', 'code');

        $users = [
            ['Shop Owner', 'owner@krushi.local', 'owner', ['MAIN', 'GD2']],
            ['Counter Cashier', 'cashier@krushi.local', 'cashier', ['MAIN']],
            ['Store Keeper', 'store@krushi.local', 'storekeeper', ['MAIN', 'GD2']],
        ];

        foreach ($users as [$name, $email, $roleSlug, $codes]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password', // hashed by the model cast
                'role_id' => Role::where('slug', $roleSlug)->value('id'),
                'email_verified_at' => now(),
                'is_active' => true,
                'locale' => 'en',
            ]);
            $user->godowns()->sync(collect($codes)->map(fn ($c) => $godowns[$c])->all());
        }
    }
}
