<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RoleMenu;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        } else {
            $this->seed(AccessSeeder::class);
        }
    }

    public function test_manager_role_is_seeded_with_shop_access_but_not_owner_menus(): void
    {
        $manager = Role::where('slug', 'manager')->first();
        $this->assertNotNull($manager);

        $keys = RoleMenu::query()
            ->where('role_id', $manager->id)
            ->where('can_view', true)
            ->join('menus', 'menus.id', '=', 'role_menu.menu_id')
            ->pluck('menus.key')
            ->all();

        $this->assertContains('sales', $keys);
        $this->assertContains('users', $keys);
        $this->assertContains('settings_company', $keys);
        $this->assertNotContains('settings_app_control', $keys);
        $this->assertNotContains('maintenance', $keys);
        $this->assertNotContains('roles', $keys);
        $this->assertNotContains('menus', $keys);
    }

    public function test_manager_cannot_assign_owner_role(): void
    {
        $managerRole = Role::where('slug', 'manager')->firstOrFail();
        $cashierRole = Role::where('slug', 'cashier')->firstOrFail();
        $ownerRole = Role::where('slug', 'owner')->firstOrFail();

        $manager = User::factory()->create([
            'email' => 'manager@test.local',
            'role_id' => $managerRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Hacker',
                'email' => 'hacker@test.local',
                'role_id' => $ownerRole->id,
                'locale' => 'en',
                'is_active' => '1',
                'password' => 'StaffPass1!',
                'password_confirmation' => 'StaffPass1!',
                'godown_ids' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'hacker@test.local']);

        $this->actingAs($manager)
            ->post(route('users.store'), [
                'name' => 'Cashier Two',
                'email' => 'cashier2@test.local',
                'role_id' => $cashierRole->id,
                'locale' => 'en',
                'is_active' => '1',
                'password' => 'StaffPass1!',
                'password_confirmation' => 'StaffPass1!',
                'godown_ids' => [],
            ])
            ->assertRedirect(route('users.index'));
    }
}
