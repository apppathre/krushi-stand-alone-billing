<?php

namespace Database\Seeders;

use App\Models\Godown;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Default owner login for fresh install (staff add the rest later). */
    public const OWNER_EMAIL = 'appathre@gmail.com';

    public const OWNER_PASSWORD = 'Prasad@123';

    public function run(): void
    {
        $godowns = Godown::pluck('id', 'code');
        $allGodownIds = $godowns->values()->all();

        $user = User::updateOrCreate(['email' => self::OWNER_EMAIL], [
            'name' => 'Shop Owner',
            'password' => self::OWNER_PASSWORD, // hashed by the model cast
            'role_id' => Role::where('slug', 'owner')->value('id'),
            'email_verified_at' => now(),
            'is_active' => true,
            'locale' => 'en',
        ]);

        // Owner can work in every seeded place; staff users are added after setup.
        if ($allGodownIds !== []) {
            $user->godowns()->sync($allGodownIds);
        }
    }
}
