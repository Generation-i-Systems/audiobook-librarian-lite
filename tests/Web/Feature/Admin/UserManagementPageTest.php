<?php

declare(strict_types=1);

namespace Tests\Web\Feature\Admin;

use App\Models\User;
use App\Support\UserRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The admin user pages.
 *
 * @see \Tests\Feature\Auth\AdministrativeAccessTest for the per-user detail page,
 *      which shows synced events and earned achievements.
 */
class UserManagementPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRoles::ADMIN]);
    }

    public function testTheUserListShowsEveryAccount(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create([
            'name' => 'Listening Member',
            'email' => 'member@example.com',
            'role' => UserRoles::USER,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Listening Member')
            ->assertSee('member@example.com')
            ->assertSee($admin->email)
            ->assertDontSee('No users found.');
    }

    /**
     * `photo_url` and `google_id` arrived in later migrations. Selecting them
     * unconditionally threw on a database migrated from an older schema, and the
     * exception was swallowed into an empty array — so the admin saw "No users
     * found." on a server that had users.
     */
    public function testTheUserListStillRendersWhenOptionalUserColumnsAreAbsent(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Legacy Schema Member', 'role' => UserRoles::USER]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['photo_url', 'google_id']);
        });

        $this->assertFalse(Schema::hasColumn('users', 'photo_url'));

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Legacy Schema Member')
            ->assertDontSee('No users found.');
    }

    /**
     * Lite hosts no book catalog and no LibriVox integration, so those roles must
     * not be offered as choices — picking one says nothing about the account.
     */
    public function testTheRoleChoicesDoNotOfferFullServerLibraryRoles(): void
    {
        $admin = $this->admin();

        foreach ([route('admin.users.create'), route('admin.users.edit', $admin->id)] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();

            foreach (['library-user', 'librivox-user', 'hybrid-user', 'LibriVox'] as $absent) {
                $response->assertDontSee($absent, false);
            }

            $response->assertSee('value="user"', false);
            $response->assertSee('value="admin"', false);
        }
    }

    public function testEditingAUserMigratedFromAFullServerKeepsItsLegacyRoleSelectable(): void
    {
        $admin = $this->admin();
        $legacy = User::factory()->create(['role' => 'library-user']);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $legacy->id))
            ->assertOk()
            ->assertSee('legacy, from the full server');
    }

    public function testTheUserListRejectsARoleLiteDoesNotRecognise(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['role' => UserRoles::USER]);

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $member->id))
            ->put(route('admin.users.update', $member->id), [
                'name' => $member->name,
                'username' => $member->username,
                'email' => $member->email,
                'role' => 'not-a-real-role',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRoles::USER, $member->fresh()->role);
    }

    /**
     * Verifying a pending signup has to leave it able to sync — that is the whole
     * point of the server.
     */
    public function testVerifyingAPendingSignupGrantsAWorkingRole(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $pending = User::factory()->create(['role' => UserRoles::UNVERIFIED]);

        $this->actingAs($admin)
            ->post(route('admin.users.verify', $pending->id))
            ->assertRedirect();

        $verified = $pending->fresh();
        $this->assertSame(UserRoles::USER, $verified->role);
        $this->assertTrue(UserRoles::isVerified($verified->role));
    }
}
