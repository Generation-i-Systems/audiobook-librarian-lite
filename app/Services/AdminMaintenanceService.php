<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminMaintenanceService
{
    public function getAllUsers(): array
    {
        try {
            $users = User::all([
                'id',
                'name',
                'username',
                'email',
                'photo_url',
                'google_id',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ]);

            return $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'photo_url' => $user->photo_url,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'google_id' => $user->google_id ?? null,
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('MySqlService getAllUsers failed: ' . $e->getMessage());

            return [];
        }
    }

    public function deleteMessage(string $messageId): bool
    {
        try {
            $message = Message::where('id', $messageId)->first();

            if (!$message) {
                return false;
            }

            $message->delete();

            return true;
        } catch (\Exception $e) {
            Log::error('MySqlService deleteMessage failed: ' . $e->getMessage());

            return false;
        }
    }
}
