<?php

namespace Database\Factories;

use App\Models\FriendQrInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FriendQrInvite>
 */
class FriendQrInviteFactory extends Factory
{
    protected $model = FriendQrInvite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ];
    }
}
