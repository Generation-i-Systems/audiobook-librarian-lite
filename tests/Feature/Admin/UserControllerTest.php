<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Contracts\DocumentStoreServiceInterface;
use App\Mail\EmailOtpMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_create_user_defaults_to_sending_welcome_email_without_a_password(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $response = $this->post('/admin/users', [
            'name' => 'New Guy',
            'username' => 'newguy',
            'email' => 'newguy@example.com',
            'role' => UserRoles::USER,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'newguy@example.com']);

        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->hasTo('newguy@example.com');
        });
        Mail::assertNotSent(EmailOtpMail::class);
    }

    public function test_create_user_with_send_otp_email_disabled_skips_email(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $response = $this->post('/admin/users', [
            'name' => 'Password User',
            'username' => 'pwuser',
            'email' => 'pwuser@example.com',
            'role' => UserRoles::USER,
            'send_otp_email' => '0',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        Mail::assertNothingSent();
    }

    public function testVerifyDialogIncludesOnlyCurrentVerificationStates(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create(['role' => 'unverified']);

        $response = $this->get(route('admin.users.index'));

        $response->assertOk();

        // Lite has a single verified role, so approving is one action.
        $response->assertSee('value="' . UserRoles::USER . '"', false);

        // Verifying approves an account; it must never escalate it to an
        // administrator. That is done deliberately from the edit page.
        foreach ([UserRoles::ADMIN, 'super-admin', 'trial-user', 'full-user'] as $role) {
            $response->assertDontSee('value="' . $role . '"', false);
        }
    }

    public function testProfileRendersCurrentPositionsAndBadgeProgress(): void
    {
        $admin = $this->admin();
        $service = Mockery::mock(DocumentStoreServiceInterface::class);
        $service->shouldReceive('getUserById')->once()->andReturn([
            'id' => $admin->id,
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => 'admin',
            'created_at' => now(),
            'listening_statistics' => [],
            'badges' => [],
            'events' => [],
        ]);
        $service->shouldReceive('getUserActivityData')->once()->with((string) $admin->id)->andReturn([
            'progress' => [[
                'book_title' => 'Current Book',
                'book_author' => 'Current Author',
                'percentage' => 42.5,
                'last_listened_at' => now(),
            ]],
            'badges_by_category' => [
                'listening' => [[
                    'name' => 'First Listen',
                    'emoji' => '🎧',
                    'is_earned' => true,
                ]],
            ],
        ]);
        $this->app->instance(DocumentStoreServiceInterface::class, $service);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $admin->id))
            ->assertOk()
            ->assertSee('Current Positions')
            ->assertSee('Current Book')
            ->assertSee('42.5%')
            ->assertSee('Badge Progress')
            ->assertSee('First Listen');
    }
}
