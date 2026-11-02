<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TableIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_owner_can_open_maintenance_and_apply_indexes(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $this->get(route('maintenance.index'))
            ->assertOk()
            ->assertSee('Server maintenance', false)
            ->assertSee('Table indexes', false)
            ->assertSee('Install / repair PDF fonts', false);

        $before = app(TableIndexService::class)->status();
        $this->post(route('maintenance.indexes'))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $after = app(TableIndexService::class)->status();
        $this->assertSame(count($before), count($after));
        $this->assertTrue(collect($after)->where('skipped', false)->every(fn ($r) => $r['present']));

        $this->post(route('maintenance.pdf-fonts'), ['force' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertTrue(\App\Support\PrintFonts::marathiAvailable());
        $this->artisan('krushi:pdf-fonts', ['--force' => true])->assertSuccessful();
    }

    public function test_install_page_redirects_when_already_installed(): void
    {
        $this->get(route('setup.install'))
            ->assertRedirect(route('login'));
    }

    public function test_profile_shows_phone_locale_and_password_tabs(): void
    {
        $owner = User::where('email', \Database\Seeders\UserSeeder::OWNER_EMAIL)->firstOrFail();
        $this->actingAs($owner);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="locale"', false)
            ->assertSee('name="avatar"', false)
            ->assertSee(route('password.update'), false);

        $this->patch(route('profile.update'), [
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => '9876543210',
            'locale' => 'mr',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('9876543210', $owner->fresh()->phone);
        $this->assertSame('mr', $owner->fresh()->locale);
    }
}
