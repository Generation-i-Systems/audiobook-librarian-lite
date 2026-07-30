<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\FriendInvitationMail;
use App\Models\FriendInvitation;
use App\Models\FriendQrInvite;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FriendService
{
    public const QR_TOKEN_TTL_MINUTES = 10;

    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {
    }

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function createQrInvite(User $user): array
    {
        $token = Str::random(32);

        $invite = FriendQrInvite::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::QR_TOKEN_TTL_MINUTES),
        ]);

        return ['token' => $token, 'expires_at' => $invite->expires_at];
    }

    public function joinViaQr(User $scanner, string $token): Friendship
    {
        return DB::transaction(function () use ($scanner, $token): Friendship {
            $invite = FriendQrInvite::where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invite === null || !$invite->isUsable()) {
                throw new InvalidArgumentException('This QR code is invalid or has expired.');
            }

            if ($invite->user_id === $scanner->id) {
                throw new InvalidArgumentException('You cannot scan your own QR code.');
            }

            $invite->forceFill(['used_at' => now()])->save();

            return $this->makeMutualFriendship($scanner->id, $invite->user_id);
        });
    }

    public function invite(User $sender, string $email): FriendInvitation
    {
        $recipient = User::where('email', $email)->first();

        if ($recipient === null) {
            throw new InvalidArgumentException('No account exists with that email address.');
        }

        if ($recipient->id === $sender->id) {
            throw new InvalidArgumentException('You cannot invite yourself.');
        }

        if ($this->areFriends($sender->id, $recipient->id)) {
            throw new InvalidArgumentException('You are already friends with this user.');
        }

        $existingPending = FriendInvitation::pending()
            ->where(function ($query) use ($sender, $recipient): void {
                $query->where(function ($pair) use ($sender, $recipient): void {
                    $pair->where('sender_id', $sender->id)->where('recipient_id', $recipient->id);
                })->orWhere(function ($pair) use ($sender, $recipient): void {
                    $pair->where('sender_id', $recipient->id)->where('recipient_id', $sender->id);
                });
            })
            ->exists();

        if ($existingPending) {
            throw new InvalidArgumentException('There is already a pending friend request between you and this user.');
        }

        $invitation = FriendInvitation::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'status' => FriendInvitation::STATUS_PENDING,
        ]);

        Mail::to($recipient->email)->send(new FriendInvitationMail($sender->name));
        $this->pushNotificationService->send(
            $recipient,
            'New friend request',
            "{$sender->name} sent you a friend request.",
        );

        return $invitation;
    }

    public function accept(FriendInvitation $invitation): Friendship
    {
        return DB::transaction(function () use ($invitation): Friendship {
            $invitation->forceFill([
                'status' => FriendInvitation::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            return $this->makeMutualFriendship($invitation->recipient_id, $invitation->sender_id);
        });
    }

    public function decline(FriendInvitation $invitation): void
    {
        $invitation->forceFill([
            'status' => FriendInvitation::STATUS_DECLINED,
            'responded_at' => now(),
        ])->save();
    }

    public function removeFriend(User $user, User $friend): void
    {
        Friendship::where('user_id', $user->id)->where('friend_id', $friend->id)->delete();
        Friendship::where('user_id', $friend->id)->where('friend_id', $user->id)->delete();
    }

    private function areFriends(int $userId, int $otherUserId): bool
    {
        return Friendship::where('user_id', $userId)->where('friend_id', $otherUserId)->exists();
    }

    /**
     * Creates both directions of the friendship and returns the row belonging to
     * $actingUserId, so callers can show "who did I just become friends with" from
     * the acting user's own perspective.
     */
    private function makeMutualFriendship(int $actingUserId, int $otherUserId): Friendship
    {
        $friendship = Friendship::firstOrCreate(['user_id' => $actingUserId, 'friend_id' => $otherUserId]);
        Friendship::firstOrCreate(['user_id' => $otherUserId, 'friend_id' => $actingUserId]);

        return $friendship;
    }
}
