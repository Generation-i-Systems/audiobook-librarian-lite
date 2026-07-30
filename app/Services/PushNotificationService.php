<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * No push provider (FCM/APNs) is wired up in this repo yet — there are no
 * provider credentials and the mobile app that would register a token lives
 * in a separate repo. This logs intent so call sites are already correct and
 * exercised by tests, and swapping in a real provider later is a one-method
 * change rather than a new integration.
 */
class PushNotificationService
{
    /**
     * @param array<string, mixed> $data
     */
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        if ($user->push_token === null) {
            return;
        }

        Log::info('[push-stub] would send push notification', [
            'user_id' => $user->id,
            'platform' => $user->push_platform,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
