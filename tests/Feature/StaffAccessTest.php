<?php

namespace Tests\Feature;

use App\Models\Godown;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    private function owner(): User
    {
        return User::where('email', UserSeeder::OWNER_EMAIL)->firstOrFail();
    }

    public function test_staff_password_drawer_and_toggle(): void
    {
        $owner = $this->owner();
        $staff = User::factory()->create([
            'email' => 'cashier@test.local',
            'role_id' => Role::where('slug', 'cashier')->value('id') ?? Role::where('slug', 'owner')->value('id'),
            'is_active' => true,
        ]);
        if ($gid = Godown::query()->value('id')) {
            $staff->godowns()->sync([$gid]);
        }

        $this->actingAs($owner)
            ->get(route('users.password', $staff))
            ->assertOk()
            ->assertSee(__('Set password'));

        $this->actingAs($owner)
            ->put(route('users.password.update', $staff), [
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ])
            ->assertRedirect(route('users.index'));

        $this->actingAs($owner)
            ->from(route('users.index'))
            ->post(route('users.toggle', $staff))
            ->assertRedirect();

        $this->assertFalse($staff->fresh()->is_active);
    }

    public function test_login_page_renders_modern_split(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-split', false)
            ->assertSee(__('Show password'), false);
    }
}
