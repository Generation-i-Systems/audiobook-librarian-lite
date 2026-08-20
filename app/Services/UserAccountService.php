<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bookmark;
use App\Models\ClientEvent;
use App\Models\ListeningEvent;
use App\Models\ListeningGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccountService
{
    public function __construct(
        private readonly ListeningActivityService $listeningActivityService = new ListeningActivityService()
    ) {
    }

    public function getUserById(mixed $identifier): ?array
    {
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

        if (Schema::hasColumn('users', 'photo_url')) {
            $columns[] = 'photo_url';
        }

        if (Schema::hasColumn('users', 'google_id')) {
            $columns[] = 'google_id';
        }

        $user = User::select($columns)->find($identifier);

        if (!$user) {
            return null;
        }

        $result = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        $photoUrl = $user->photo_url ?? $user->photoUrl ?? null;

        if ($photoUrl) {
            $result['photo_url'] = $photoUrl;
        }

        $googleId = $user->google_id ?? $user->googleId ?? null;

        if ($googleId) {
            $result['google_id'] = $googleId;
        }

        $result['groups'] = $user->groups()->get(['groups.id', 'groups.name'])->toArray();
        $result['friendships'] = $user->friendships()->with('friend:id,name,username,email')->get()->toArray();
        $result['sent_friend_invitations'] = $user->sentFriendInvitations()
            ->with('recipient:id,name,username,email')->get()->toArray();
        $result['received_friend_invitations'] = $user->receivedFriendInvitations()
            ->with('sender:id,name,username,email')->get()->toArray();
        $result['badges'] = $user->badges()->with('badge')->orderByDesc('earned_at')->get()->toArray();
        $result['book_statuses'] = $user->bookStatuses()->get()->toArray();
        $result['bookmarks'] = Bookmark::query()->where('user_id', $user->id)->orderByDesc('updated_at')->get()->toArray();
        $result['listening_goals'] = ListeningGoal::query()->where('user_id', $user->id)->orderByDesc('updated_at')->get()->toArray();

        $events = ListeningEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('timestamp_ms')
            ->limit(50)
            ->get();
        $sessions = $this->listeningActivityService->getSessions($user->id);
        $allEventCount = ListeningEvent::query()->where('user_id', $user->id)->count();

        $result['listening_statistics'] = [
            'event_count' => $allEventCount,
            'session_count' => $sessions->count(),
            'total_seconds' => (int) $sessions->sum('seconds_listened'),
            'books_started' => $sessions
                ->map(static fn (object $session): string => $session->title . "\0" . $session->author)
                ->unique()
                ->count(),
            'active_days' => $sessions->pluck('listening_date')->unique()->count(),
            'current_streak' => $this->listeningActivityService->getCurrentStreak($user->id),
            'longest_streak' => $this->listeningActivityService->getLongestStreak($user->id),
            'last_listened_at' => $events->first() === null
                ? null
                : Carbon::createFromTimestampMs((int) $events->first()->timestamp_ms)->toDateTimeString(),
        ];
        $result['events'] = $events->map(static fn (ListeningEvent $event): array => [
            'event_type' => $event->event_type,
            'title' => $event->title,
            'author' => $event->author,
            'position_ms' => $event->position_ms,
            'occurred_at' => Carbon::createFromTimestampMs((int) $event->timestamp_ms)->toDateTimeString(),
            'device_id' => $event->device_id,
        ])->all();

        return $result;
    }

    public function getUserByCredentials(mixed $credentials): ?array
    {
        if (empty($credentials['password'])) {
            return null;
        }

        $user = null;

        if (!empty($credentials['email'])) {
            $user = User::where('email', $credentials['email'])->first();
        } elseif (!empty($credentials['username'])) {
            $user = User::where('username', $credentials['username'])->first();
        }

        if (!$user) {
            return null;
        }

        if (Hash::check($credentials['password'], $user->getAuthPassword())) {
            return $user->makeVisible(['password'])->toArray();
        }

        return null;
    }

    public function getUserByRememberToken(mixed $identifier, mixed $token): ?array
    {
        $user = User::where('id', $identifier)->where('remember_token', $token)->first();

        return $user ? $user->toArray() : null;
    }

    public function createUser(array $data): string
    {
        $username = $data['username'] ?? explode('@', $data['email'])[0];
        $originalUsername = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        $userAttributes = [
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'library-user',
            'email_verified_at' => $data['email_verified_at'] ?? null,
        ];

        foreach (['google_id', 'facebook_id', 'apple_id', 'discord_id'] as $oauthColumn) {
            if (Schema::hasColumn('users', $oauthColumn)) {
                $userAttributes[$oauthColumn] = $data[$oauthColumn] ?? null;
            }
        }

        $user = User::create($userAttributes);

        return (string) $user->id;
    }

    public function updateUser(string $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);

        return $user;
    }

    public function deleteUser(string $id): int
    {
        return User::where('id', $id)->delete();
    }

    public function permanentlyDeleteUser(string $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                return false;
            }

            $user->tokens()->delete();
            DB::table('api_tokens')->where('user_id', $user->id)->delete();
            ClientEvent::where('user_id', $user->id)->delete();

            return $user->forceDelete();
        });
    }

    public function getUserByEmail(string $email): ?array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return null;
        }

        return $user->makeVisible(['password'])->toArray();
    }

    public function getUserByAppleId(string $appleId): ?array
    {
        $appleId = trim($appleId);

        if ($appleId === '') {
            return null;
        }

        $user = User::where('apple_id', $appleId)->first();

        if (!$user) {
            return null;
        }

        return $user->makeVisible(['password'])->toArray();
    }

    public function getUserByDiscordId(string $discordId): ?array
    {
        $discordId = trim($discordId);

        if ($discordId === '') {
            return null;
        }

        $user = User::where('discord_id', $discordId)->first();

        if (!$user) {
            return null;
        }

        return $user->makeVisible(['password'])->toArray();
    }

    public function userExistsByEmail(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function userExistsByUsername(string $username): bool
    {
        return User::where('username', $username)->exists();
    }

    public function validateUserCredentials(mixed $user, array $credentials): bool
    {
        if (!isset($credentials['password'])) {
            return false;
        }

        if (is_array($user)) {
            $user = User::find($user['id'] ?? null);

            if (!$user) {
                return false;
            }
        }

        return Hash::check($credentials['password'], $user->password);
    }

    public function getUserByUsername(string $username): ?array
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return null;
        }

        return $user->makeVisible(['password'])->toArray();
    }

    public function getAdminUsers(): array
    {
        return User::whereIn('role', ['admin', 'super-admin'])->get()->toArray();
    }

    public function isAdmin(string $userId): bool
    {
        $user = User::find($userId);

        return $user && in_array($user->role, ['admin', 'super-admin'], true);
    }

    public function updateRememberToken(string $identifier, string $token): void
    {
        $user = User::find($identifier);

        if ($user) {
            $user->setRememberToken($token);
            $user->save();
        }
    }

    public function getPendingAccountRequests(): array
    {
        try {
            return DB::table('account_requests')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('MySqlService getPendingAccountRequests failed: ' . $e->getMessage());

            return [];
        }
    }

    public function getAccountRequest(string $id): ?array
    {
        try {
            $request = DB::table('account_requests')->where('id', $id)->first();

            return $request ? (array) $request : null;
        } catch (\Exception $e) {
            Log::error('MySqlService getAccountRequest failed: ' . $e->getMessage());

            return null;
        }
    }

    public function approveAccountRequest(string $id): bool
    {
        try {
            DB::beginTransaction();

            $request = DB::table('account_requests')->where('id', $id)->first();

            if (!$request) {
                DB::rollBack();

                return false;
            }

            DB::table('account_requests')
                ->where('id', $id)
                ->update(['status' => 'approved', 'updated_at' => now()]);

            User::create([
                'name' => $request->name ?? '',
                'email' => $request->email ?? '',
                'username' => $request->username ?? '',
                'password' => Hash::make($request->password ?? Str::random(10)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MySqlService approveAccountRequest failed: ' . $e->getMessage());

            return false;
        }
    }

    public function rejectAccountRequest(string $id): bool
    {
        try {
            $updated = DB::table('account_requests')
                ->where('id', $id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

            return $updated > 0;
        } catch (\Exception $e) {
            Log::error('MySqlService rejectAccountRequest failed: ' . $e->getMessage());

            return false;
        }
    }
}
