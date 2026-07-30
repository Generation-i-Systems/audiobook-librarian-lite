<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\FriendInvitationMail;
use App\Models\FriendInvitation;
use App\Models\FriendQrInvite;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class FriendServiceTest extends TestCase
{
    use RefreshDatabase;

    private FriendService $friendService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->friendService = app(FriendService::class);
    }

    public function testInviteRejectsSelfInvite(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->invite($user, $user->email);
    }

    public function testInviteRejectsWhenAlreadyFriends(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::factory()->create(['user_id' => $user->id, 'friend_id' => $friend->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->invite($user, $friend->email);
    }

    public function testInviteRejectsWhenPendingInviteAlreadyExistsInEitherDirection(): void
    {
        Mail::fake();
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        FriendInvitation::factory()->create([
            'sender_id' => $recipient->id,
            'recipient_id' => $sender->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->invite($sender, $recipient->email);
    }

    public function testInviteAllowsNewRequestAfterPriorOneWasDeclined(): void
    {
        Mail::fake();
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => FriendInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ]);

        $invitation = $this->friendService->invite($sender, $recipient->email);

        $this->assertSame(FriendInvitation::STATUS_PENDING, $invitation->status);
        Mail::assertSent(FriendInvitationMail::class);
    }

    public function testJoinViaQrRejectsSelfScan(): void
    {
        $user = User::factory()->create();
        [$token] = array_values($this->friendService->createQrInvite($user));

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->joinViaQr($user, $token);
    }

    public function testJoinViaQrMarksTokenUsedSoItCannotBeReplayed(): void
    {
        $inviter = User::factory()->create();
        $scanner = User::factory()->create();
        $invite = $this->friendService->createQrInvite($inviter);

        $this->friendService->joinViaQr($scanner, $invite['token']);

        $secondScanner = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        $this->friendService->joinViaQr($secondScanner, $invite['token']);
    }

    public function testJoinViaQrCreatesBothDirectionsOfTheFriendship(): void
    {
        $inviter = User::factory()->create();
        $scanner = User::factory()->create();
        $invite = $this->friendService->createQrInvite($inviter);

        $this->friendService->joinViaQr($scanner, $invite['token']);

        $this->assertDatabaseHas('friendships', ['user_id' => $scanner->id, 'friend_id' => $inviter->id]);
        $this->assertDatabaseHas('friendships', ['user_id' => $inviter->id, 'friend_id' => $scanner->id]);
    }

    public function testAcceptCreatesFriendshipAndMarksInvitationAccepted(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $invitation = FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $friendship = $this->friendService->accept($invitation);

        $this->assertSame($recipient->id, $friendship->user_id);
        $this->assertSame($sender->id, $friendship->friend_id);
        $this->assertSame(FriendInvitation::STATUS_ACCEPTED, $invitation->fresh()->status);
    }

    public function testDeclineDoesNotCreateAFriendship(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $invitation = FriendInvitation::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->friendService->decline($invitation);

        $this->assertSame(FriendInvitation::STATUS_DECLINED, $invitation->fresh()->status);
        $this->assertDatabaseMissing('friendships', ['user_id' => $sender->id, 'friend_id' => $recipient->id]);
        $this->assertDatabaseMissing('friendships', ['user_id' => $recipient->id, 'friend_id' => $sender->id]);
    }

    public function testRemoveFriendDeletesBothDirections(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Friendship::factory()->create(['user_id' => $userA->id, 'friend_id' => $userB->id]);
        Friendship::factory()->create(['user_id' => $userB->id, 'friend_id' => $userA->id]);

        $this->friendService->removeFriend($userA, $userB);

        $this->assertDatabaseMissing('friendships', ['user_id' => $userA->id, 'friend_id' => $userB->id]);
        $this->assertDatabaseMissing('friendships', ['user_id' => $userB->id, 'friend_id' => $userA->id]);
    }

    public function testJoinViaQrRejectsUnknownToken(): void
    {
        $scanner = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->joinViaQr($scanner, 'not-a-real-token');
    }

    public function testJoinViaQrRejectsExpiredToken(): void
    {
        $inviter = User::factory()->create();
        $scanner = User::factory()->create();
        FriendQrInvite::factory()->create([
            'user_id' => $inviter->id,
            'token_hash' => hash('sha256', 'stale-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->friendService->joinViaQr($scanner, 'stale-token');
    }
}
