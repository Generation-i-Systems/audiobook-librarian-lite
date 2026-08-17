<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\NewUserRegistrationNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdministrativeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function testLandingPageExplainsTheProjectAndProvidesTheExpectedLinks(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk()
            ->assertSee('AbLibrarian Lite')
            ->assertSee('https://www.ablibrarian.com/', false)
            ->assertSee('https://github.com/Generation-i-Systems/audiobook-librarian-lite', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('admin.login'), false);
    }

    public function testWebRegistrationCreatesAnUnverifiedUserAndNotifiesAdministrators(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'name' => 'Requested User',
            'username' => 'requested-user',
            'email' => 'requested@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);

        $response->assertRedirect(route('landing'));
        $this->assertDatabaseHas('users', [
            'email' => 'requested@example.com',
            'role' => 'unverified',
        ]);
        Mail::assertSent(NewUserRegistrationNotification::class);
    }

    public function testAdminCanSignInAndReachUserManagement(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secure-password',
            'role' => 'admin',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'secure-password',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticated('web');
        $this->assertSame((string) $admin->id, (string) auth()->id());
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('User Management');
        $this->get(route('admin.users.show', $admin->id))
            ->assertOk()
            ->assertSee('Profile Info');
        $this->get(route('admin.users.edit', $admin->id))
            ->assertOk()
            ->assertSee('Profile Information');
    }

    public function testNonAdminCannotUseTheAdministratorSignIn(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'secure-password',
            'role' => 'user',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'secure-password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function testGuestIsSentToTheAdministratorSignInForAnAdminPage(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('admin.login'));
    }
}
