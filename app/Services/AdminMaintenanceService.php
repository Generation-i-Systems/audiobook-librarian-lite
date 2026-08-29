<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminMaintenanceService
{
    /**
     * List every account for the admin user table.
     *
     * `photo_url` and `google_id` are selected only when the column actually
     * exists: lite runs against databases migrated from older schemas, and
     * selecting a missing column used to throw, get swallowed by the catch
     * below, and render the whole admin user list as "No users found".
     */
    public function getAllUsers(): array
    {
        try {
            $columns = [
                'id',
                'name',
                'username',
                'email',
                'role',
                'email_verified_at',
                'created_at',
                'updated_at',
            ];

            foreach (['photo_url', 'google_id'] as $optionalColumn) {
                if (Schema::hasColumn('users', $optionalColumn)) {
                    $columns[] = $optionalColumn;
                }
            }

            $users = User::all($columns);

            return $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'photo_url' => $user->photo_url ?? null,
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
