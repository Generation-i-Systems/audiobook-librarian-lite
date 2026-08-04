<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\EmailOtpMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
            'role' => 'user',
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
            'role' => 'user',
            'send_otp_email' => '0',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        Mail::assertNothingSent();
    }
}
