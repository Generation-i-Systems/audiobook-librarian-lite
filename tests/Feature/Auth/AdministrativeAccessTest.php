<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\NewUserRegistrationNotification;
use App\Models\Badge;
use App\Models\ListeningEvent;
use App\Models\User;
use App\Models\UserBadge;
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
        $this->get(route('admin.badges.index'))
            ->assertOk()
            ->assertSee('All Badges');
        $this->get(route('admin.users.show', $admin->id))
            ->assertOk()
            ->assertSee('Profile Info')
            ->assertSee('User Data')
            ->assertSee('Bookmarks')
            ->assertSee('Listening goals')
            ->assertSee('Achievements')
            ->assertSee('Listening Statistics');
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

    public function testAdminUserPageShowsEventSourcedStatisticsAndAchievements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'full-user']);
        $badge = Badge::query()->create([
            'key' => 'first-session',
            'name' => 'First Session',
            'description' => 'Completed a first listening session.',
            'category' => 'listening',
            'tier' => 'bronze',
            'points' => 10,
            'criteria' => ['sessions' => 1],
        ]);
        UserBadge::query()->create([
            'user_id' => $member->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);
        ListeningEvent::query()->create([
            'id' => 'admin-user-page-session-end',
            'user_id' => $member->id,
            'title' => 'The Event-Sourced Book',
            'author' => 'A. Listener',
            'event_type' => 'SESSION_END',
            'timestamp_ms' => now()->getTimestampMs(),
            'position_ms' => 120000,
            'metadata' => ['sessionDurationMs' => 120000],
            'device_id' => 'admin-test-device',
            'timezone' => 'UTC',
            'sync_status' => 'SYNCED',
            'created_at' => now()->getTimestampMs(),
            'synced_at' => now()->getTimestampMs(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $member->id))
            ->assertOk()
            ->assertSee('Listening Statistics')
            ->assertSee('Synced events:')
            ->assertSee('Achievements')
            ->assertSee('First Session')
            ->assertSee('Recent Synced Events')
            ->assertSee('The Event-Sourced Book');
    }

    public function testGuestIsSentToTheAdministratorSignInForAnAdminPage(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('admin.login'));
    }
}
