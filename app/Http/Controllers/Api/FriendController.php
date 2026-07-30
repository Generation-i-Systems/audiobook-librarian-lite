<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendInvitation;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FriendController extends Controller
{
    public function __construct(private readonly FriendService $friendService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $friends = Friendship::where('user_id', $this->authenticatedUser($request)->id)
            ->with('friend')
            ->get()
            ->map(fn (Friendship $friendship) => $this->formatUser($friendship->friend));

        return response()->json(['friends' => $friends->values()]);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $friend = User::find($userId);

        if ($friend === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $this->friendService->removeFriend($this->authenticatedUser($request), $friend);

        return response()->json([], 204);
    }

    public function createQrInvite(Request $request): JsonResponse
    {
        $invite = $this->friendService->createQrInvite($this->authenticatedUser($request));

        return response()->json([
            'token' => $invite['token'],
            'expires_at' => $invite['expires_at']->toIso8601String(),
        ]);
    }

    public function joinViaQr(Request $request, string $token): JsonResponse
    {
        try {
            $friendship = $this->friendService->joinViaQr($this->authenticatedUser($request), $token);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['friend' => $this->formatUser($friendship->friend)]);
    }

    public function sendInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $invitation = $this->friendService->invite($this->authenticatedUser($request), $validated['email']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->formatInvitation($invitation), 201);
    }

    public function listInvitations(Request $request): JsonResponse
    {
        $direction = $request->query('direction');
        $userId = $this->authenticatedUser($request)->id;

        $query = FriendInvitation::with(['sender', 'recipient']);

        if ($direction === 'sent') {
            $query->where('sender_id', $userId);
        } elseif ($direction === 'received') {
            $query->where('recipient_id', $userId);
        } else {
            $query->where(function ($q) use ($userId): void {
                $q->where('sender_id', $userId)->orWhere('recipient_id', $userId);
            });
        }

        $invitations = $query->orderByDesc('created_at')->get()
            ->map(fn (FriendInvitation $invitation) => $this->formatInvitation($invitation));

        return response()->json(['invitations' => $invitations->values()]);
    }

    public function acceptInvitation(Request $request, int $invitationId): JsonResponse
    {
        $invitation = $this->findPendingInvitationForRecipient($request, $invitationId);

        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $friendship = $this->friendService->accept($invitation);

        return response()->json(['friend' => $this->formatUser($friendship->friend)]);
    }

    public function declineInvitation(Request $request, int $invitationId): JsonResponse
    {
        $invitation = $this->findPendingInvitationForRecipient($request, $invitationId);

        if ($invitation instanceof JsonResponse) {
            return $invitation;
        }

        $this->friendService->decline($invitation);

        return response()->json([], 204);
    }

    public function unshownInvitations(Request $request): JsonResponse
    {
        $invitations = FriendInvitation::pending()
            ->unshown()
            ->where('recipient_id', $this->authenticatedUser($request)->id)
            ->with('sender')
            ->get()
            ->map(fn (FriendInvitation $invitation) => $this->formatInvitation($invitation));

        return response()->json(['invitations' => $invitations->values()]);
    }

    public function markInvitationsShown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitation_ids' => ['required', 'array', 'min:1'],
            'invitation_ids.*' => ['integer'],
        ]);

        $markedCount = FriendInvitation::where('recipient_id', $this->authenticatedUser($request)->id)
            ->whereIn('id', $validated['invitation_ids'])
            ->update(['is_shown' => true]);

        return response()->json(['success' => true, 'marked_count' => $markedCount]);
    }

    private function findPendingInvitationForRecipient(Request $request, int $invitationId): FriendInvitation|JsonResponse
    {
        $invitation = FriendInvitation::find($invitationId);

        if ($invitation === null || $invitation->recipient_id !== $this->authenticatedUser($request)->id) {
            return response()->json(['error' => 'Invitation not found'], 404);
        }

        if ($invitation->status !== FriendInvitation::STATUS_PENDING) {
            return response()->json(['error' => 'This invitation has already been responded to'], 422);
        }

        return $invitation;
    }

    private function authenticatedUser(Request $request): User
    {
        return User::findOrFail((int) $request->user()?->getAuthIdentifier());
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvitation(FriendInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'sender' => $this->formatUser($invitation->sender),
            'recipient' => $this->formatUser($invitation->recipient),
            'created_at' => $invitation->created_at?->toIso8601String(),
            'responded_at' => $invitation->responded_at?->toIso8601String(),
        ];
    }
}
