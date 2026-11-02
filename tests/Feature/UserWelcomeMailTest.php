<?php

namespace Tests\Feature;

use App\Mail\UserWelcomeMail;
use App\Models\MailSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', UserSeeder::OWNER_EMAIL)->exists()) {
            $this->seed();
        }
    }

    public function test_creating_user_sends_welcome_mail_when_smtp_enabled(): void
    {
        Mail::fake();

        MailSetting::current()->update([
            'is_enabled' => true,
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'from_address' => 'noreply@example.test',
            'from_name' => 'Krushi',
        ]);

        $owner = User::where('email', UserSeeder::OWNER_EMAIL)->firstOrFail();
        $roleId = Role::where('slug', 'cashier')->value('id') ?? Role::where('slug', 'owner')->value('id');

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'New Staff',
                'email' => 'newstaff@example.test',
                'role_id' => $roleId,
                'locale' => 'en',
                'is_active' => '1',
                'password' => 'StaffPass1!',
                'password_confirmation' => 'StaffPass1!',
                'godown_ids' => [],
            ])
            ->assertRedirect(route('users.index'));

        Mail::assertSent(UserWelcomeMail::class, function (UserWelcomeMail $mail) {
            return $mail->user->email === 'newstaff@example.test'
                && $mail->plainPassword === 'StaffPass1!';
        });
    }

    public function test_creating_user_skips_mail_when_smtp_disabled(): void
    {
        Mail::fake();

        MailSetting::current()->update(['is_enabled' => false, 'host' => null]);

        $owner = User::where('email', UserSeeder::OWNER_EMAIL)->firstOrFail();
        $roleId = Role::where('slug', 'cashier')->value('id') ?? Role::where('slug', 'owner')->value('id');

        $this->actingAs($owner)
            ->post(route('users.store'), [
                'name' => 'Quiet Staff',
                'email' => 'quiet@example.test',
                'role_id' => $roleId,
                'locale' => 'en',
                'is_active' => '1',
                'password' => 'StaffPass1!',
                'password_confirmation' => 'StaffPass1!',
                'godown_ids' => [],
            ])
            ->assertRedirect(route('users.index'));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('users', ['email' => 'quiet@example.test']);
    }
}
