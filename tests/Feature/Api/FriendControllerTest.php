<?php

namespace Tests\Feature\Api;

use App\Mail\FriendInvitationMail;
use App\Models\FriendInvitation;
use App\Models\FriendQrInvite;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FriendControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsUser(): array
    {
        $user = User::factory()->create(['role' => 'full-user']);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, ['Authorization' => 'Bearer ' . $token]];
    }

    /**
     * ApiAuth's testing-environment bypass checks Auth::guard($guard)->check() before
     * looking at the request's own Bearer token, and guard instances cache their
     * resolved user across requests within a single test method. Without clearing that
     * cache before every request, a test making requests as two different users would
     * silently keep authenticating as whichever user the previous request resolved,
     * ignoring the Bearer token actually attached to the current request.
     */
    protected function asUser(array $headers)
    {
        Auth::forgetGuards();

        return $this->withHeaders($headers);
    }

    public function testCreateAndJoinViaQrCreatesMutualFriendship(): void
    {
        [$inviter, $inviterHeaders] = $this->actingAsUser();

        $qrResponse = $this->asUser($inviterHeaders)->postJson('/api/v1/friends/qr');
        $qrResponse->assertOk()->assertJsonStructure(['token', 'expires_at']);
        $token = $qrResponse->json('token');

        [$scanner, $scannerHeaders] = $this->actingAsUser();

        $joinResponse = $this->asUser($scannerHeaders)->postJson("/api/v1/friends/qr/{$token}/join");
        $joinResponse->assertOk()->assertJsonPath('friend.id', $inviter->id);

        $this->assertDatabaseHas('friendships', ['user_id' => $inviter->id, 'friend_id' => $scanner->id]);
        $this->assertDatabaseHas('friendships', ['user_id' => $scanner->id, 'friend_id' => $inviter->id]);
    }

    public function testJoinViaQrRejectsExpiredToken(): void
    {
        $inviter = User::factory()->create();
        $qrInvite = FriendQrInvite::factory()->create([
            'user_id' => $inviter->id,
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subMinute(),
        ]);

        [, $scannerHeaders] = $this->actingAsUser();

        $response = $this->asUser($scannerHeaders)->postJson('/api/v1/friends/qr/expired-token/join');

        $response->assertStatus(422);
        $this->assertNull($qrInvite->fresh()->used_at);
    }

    public function testJoinViaQrRejectsAlreadyUsedToken(): void
    {
        $inviter = User::factory()->create();
        FriendQrInvite::factory()->create([
            'user_id' => $inviter->id,
            'token_hash' => hash('sha256', 'used-token'),
            'used_at' => now(),
        ]);

        [, $scannerHeaders] = $this->actingAsUser();

        $response = $this->asUser($scannerHeaders)->postJson('/api/v1/friends/qr/used-token/join');

        $response->assertStatus(422);
    }

    public function testJoinViaQrRejectsSelfScan(): void
    {
        [$inviter, $inviterHeaders] = $this->actingAsUser();

        $qrResponse = $this->asUser($inviterHeaders)->postJson('/api/v1/friends/qr');
        $token = $qrResponse->json('token');

        $response = $this->asUser($inviterHeaders)->postJson("/api/v1/friends/qr/{$token}/join");

        $response->assertStatus(422)->assertJsonPath('error', 'You cannot scan your own QR code.');
    }

    public function testSendInvitationRejectsNonExistentEmail(): void
    {
        [, $senderHeaders] = $this->actingAsUser();

        $response = $this->asUser($senderHeaders)->postJson('/api/v1/friends/invitations', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'No account exists with that email address.');
    }

    public function testSendInvitationSendsMailAndCreatesPendingInvitation(): void
    {
        Mail::fake();

        [$sender, $senderHeaders] = $this->actingAsUser();
        $recipient = User::factory()->create();

        $response = $this->asUser($senderHeaders)->postJson('/api/v1/friends/invitations', [
            'email' => $recipient->email,
        ]);

        $response->assertStatus(201)->assertJsonPath('status', FriendInvitation::STATUS_PENDING);

        $this->assertDatabaseHas('friend_invitations', [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => FriendInvitation::STATUS_PENDING,
        ]);

        Mail::assertSent(FriendInvitationMail::class);
    }

    public function testSendInvitationRejectsDuplicatePendingInvite(): void
    {
        Mail::fake();

        [$sender, $senderHeaders] = $this->actingAsUser();
        $recipient = User::factory()->create();

        $this->asUser($senderHeaders)->postJson('/api/v1/friends/invitations', ['email' => $recipient->email]);
        $response = $this->asUser($senderHeaders)->postJson('/api/v1/friends/invitations', ['email' => $recipient->email]);

        $response->assertStatus(422);
    }

    public function testAcceptInvitationCreatesMutualFriendshipAndFriendCannotAcceptOthersInvite(): void
    {
        Mail::fake();

        [$sender, $senderHeaders] = $this->actingAsUser();
        [$recipient, $recipientHeaders] = $this->actingAsUser();

        $invitation = FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        [, $strangerHeaders] = $this->actingAsUser();
        $this->asUser($strangerHeaders)
            ->postJson("/api/v1/friends/invitations/{$invitation->id}/accept")
            ->assertStatus(404);

        $response = $this->asUser($recipientHeaders)
            ->postJson("/api/v1/friends/invitations/{$invitation->id}/accept");

        $response->assertOk()->assertJsonPath('friend.id', $sender->id);

        $this->assertDatabaseHas('friendships', ['user_id' => $sender->id, 'friend_id' => $recipient->id]);
        $this->assertDatabaseHas('friendships', ['user_id' => $recipient->id, 'friend_id' => $sender->id]);
        $this->assertDatabaseHas('friend_invitations', ['id' => $invitation->id, 'status' => FriendInvitation::STATUS_ACCEPTED]);
    }

    public function testDeclineInvitationDoesNotCreateFriendship(): void
    {
        [$sender] = $this->actingAsUser();
        [$recipient, $recipientHeaders] = $this->actingAsUser();

        $invitation = FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->asUser($recipientHeaders)
            ->postJson("/api/v1/friends/invitations/{$invitation->id}/decline");

        $response->assertStatus(204);
        $this->assertDatabaseHas('friend_invitations', ['id' => $invitation->id, 'status' => FriendInvitation::STATUS_DECLINED]);
        $this->assertDatabaseMissing('friendships', ['user_id' => $sender->id, 'friend_id' => $recipient->id]);
    }

    public function testUnshownAndMarkShownRoundTrip(): void
    {
        [$sender] = $this->actingAsUser();
        [$recipient, $recipientHeaders] = $this->actingAsUser();

        $invitation = FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $unshown = $this->asUser($recipientHeaders)->getJson('/api/v1/friends/invitations/unshown');
        $unshown->assertOk()->assertJsonCount(1, 'invitations');

        $this->asUser($recipientHeaders)->postJson('/api/v1/friends/invitations/mark-shown', [
            'invitation_ids' => [$invitation->id],
        ])->assertOk()->assertJsonPath('marked_count', 1);

        $this->asUser($recipientHeaders)->getJson('/api/v1/friends/invitations/unshown')
            ->assertOk()->assertJsonCount(0, 'invitations');
    }

    public function testUnfriendRemovesBothDirections(): void
    {
        [$userA, $userAHeaders] = $this->actingAsUser();
        [$userB] = $this->actingAsUser();

        Friendship::factory()->create(['user_id' => $userA->id, 'friend_id' => $userB->id]);
        Friendship::factory()->create(['user_id' => $userB->id, 'friend_id' => $userA->id]);

        $this->asUser($userAHeaders)->deleteJson("/api/v1/friends/{$userB->id}")->assertStatus(204);

        $this->assertDatabaseMissing('friendships', ['user_id' => $userA->id, 'friend_id' => $userB->id]);
        $this->assertDatabaseMissing('friendships', ['user_id' => $userB->id, 'friend_id' => $userA->id]);
    }

    public function testIndexListsFriends(): void
    {
        [$userA, $userAHeaders] = $this->actingAsUser();
        $userB = User::factory()->create();

        Friendship::factory()->create(['user_id' => $userA->id, 'friend_id' => $userB->id]);

        $response = $this->asUser($userAHeaders)->getJson('/api/v1/friends');

        $response->assertOk()->assertJsonPath('friends.0.id', $userB->id);
    }
}
