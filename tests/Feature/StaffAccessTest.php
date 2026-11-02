<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', 'owner@krushi.local')->exists()) {
            $this->seed();
        }
    }

    private function owner(): User
    {
        return User::where('email', 'owner@krushi.local')->firstOrFail();
    }

    public function test_staff_password_drawer_and_toggle(): void
    {
        $owner = $this->owner();
        $staff = User::where('email', 'cashier@krushi.local')->firstOrFail();

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
