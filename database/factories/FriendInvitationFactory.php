<?php

namespace Database\Factories;

use App\Models\FriendInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FriendInvitation>
 */
class FriendInvitationFactory extends Factory
{
    protected $model = FriendInvitation::class;

    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'status' => FriendInvitation::STATUS_PENDING,
            'is_shown' => false,
            'responded_at' => null,
        ];
    }
}
