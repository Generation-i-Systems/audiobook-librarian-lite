<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentStoreServiceInterface;
use App\Contracts\DocumentStatsServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MySqlService implements DocumentStoreServiceInterface, DocumentStatsServiceInterface
{
    private ?UserLibraryStateService $userLibraryStateService = null;
    private ?UserAccountService $userAccountService = null;
    private ?UserReadingStatsService $userReadingStatsService = null;
    private ?WorkflowMessagingService $workflowMessagingService = null;
    private ?UserActivityService $userActivityService = null;
    private ?GenericDocumentService $genericDocumentService = null;
    private ?AdminMaintenanceService $adminMaintenanceService = null;
    private ?TokenMaintenanceService $tokenMaintenanceService = null;

    private function getUserLibraryStateService(): UserLibraryStateService
    {
        return $this->userLibraryStateService ??= app(UserLibraryStateService::class);
    }

    private function getUserAccountService(): UserAccountService
    {
        return $this->userAccountService ??= app(UserAccountService::class);
    }

    private function getUserReadingStatsService(): UserReadingStatsService
    {
        return $this->userReadingStatsService ??= app(UserReadingStatsService::class);
    }

    private function getWorkflowMessagingService(): WorkflowMessagingService
    {
        return $this->workflowMessagingService ??= app(WorkflowMessagingService::class);
    }

    private function getUserActivityService(): UserActivityService
    {
        return $this->userActivityService ??= app(UserActivityService::class);
    }

    private function getGenericDocumentService(): GenericDocumentService
    {
        return $this->genericDocumentService ??= app(GenericDocumentService::class);
    }

    private function getAdminMaintenanceService(): AdminMaintenanceService
    {
        return $this->adminMaintenanceService ??= app(AdminMaintenanceService::class);
    }

    private function getTokenMaintenanceService(): TokenMaintenanceService
    {
        return $this->tokenMaintenanceService ??= app(TokenMaintenanceService::class);
    }

    /**
     * Lite has no book library — always returns null. Kept only because
     * BookmarkApiController and ApiHealthController still call it.
     */
    public function getBook(string $id, ?int $userId = null): ?array
    {
        return null;
    }


    /**
     * Lite has no book library — always returns an empty paginated result.
     * Kept only because ApiHealthController still calls it.
     */
    public function listBooks(
        int $page = 1,
        int $perPage = 24,
        array $filters = [],
        bool $withRelated = true,
        string $sort = 'title',
        string $order = 'asc',
        bool $includeAllBooks = false,
        ?int $userId = null
    ): array {
        return [
            'data' => [],
            'total' => 0,
            'perPage' => $perPage,
            'per_page' => $perPage,
            'currentPage' => $page,
            'current_page' => $page,
            'lastPage' => 1,
            'last_page' => 1,
        ];
    }

    public function paginateAuthorsWithStats(
        int $perPage = 25,
        ?string $search = null,
        string $sort = 'name',
        string $direction = 'asc'
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = DB::table('authors')
            ->leftJoin('author_book', 'authors.id', '=', 'author_book.author_id')
            ->leftJoin('books', function ($join) {
                $join->on('author_book.book_id', '=', 'books.id')
                    ->whereNull('books.deleted_at')
                    ->where('books.directory_exists', true)
                    ->where('books.needs_review', false);
            })
            ->whereNull('authors.deleted_at')
            ->groupBy('authors.id', 'authors.name', 'authors.updated_at')
            ->select(
                'authors.id',
                'authors.name',
                'authors.updated_at',
                DB::raw('COUNT(DISTINCT books.id) as book_count')
            );

        if ($search) {
            $query->where('authors.name', 'LIKE', '%' . $search . '%');
        }

        if ($sort === 'books') {
            $query->orderBy('book_count', $direction)->orderBy('authors.name', 'asc');
        } else {
            $query->orderBy('authors.name', $direction);
        }

        return $query->paginate($perPage);
    }

    public function getAuthorsByIdsWithStats(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('authors')
            ->leftJoin('author_book', 'authors.id', '=', 'author_book.author_id')
            ->leftJoin('books', function ($join) {
                $join->on('author_book.book_id', '=', 'books.id')
                    ->whereNull('books.deleted_at')
                    ->where('books.directory_exists', true)
                    ->where('books.needs_review', false);
            })
            ->whereNull('authors.deleted_at')
            ->whereIn('authors.id', $ids)
            ->groupBy('authors.id', 'authors.name', 'authors.updated_at')
            ->orderBy('authors.name')
            ->select(
                'authors.id',
                'authors.name',
                'authors.updated_at',
                DB::raw('COUNT(DISTINCT books.id) as book_count')
            )
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (string) $row->id,
            'name' => (string) $row->name,
            'updatedAt' => $row->updated_at,
            'bookCount' => (int) $row->book_count,
        ])->toArray();
    }

    // --- Placeholder Implementations ---

    public function getUserById($identifier)
    {
        return $this->getUserAccountService()->getUserById($identifier);
    }

    public function getUserByCredentials($credentials)
    {
        return $this->getUserAccountService()->getUserByCredentials($credentials);
    }

    public function getUserByRememberToken($identifier, $token)
    {
        return $this->getUserAccountService()->getUserByRememberToken($identifier, $token);
    }

    public function createUser(array $data)
    {
        return $this->getUserAccountService()->createUser($data);
    }

    public function updateUser(string $id, array $data)
    {
        return $this->getUserAccountService()->updateUser($id, $data);
    }

    public function deleteUser(string $id)
    {
        return $this->getUserAccountService()->deleteUser($id);
    }

    public function permanentlyDeleteUser(string $id): bool
    {
        return $this->getUserAccountService()->permanentlyDeleteUser($id);
    }

    /**
     * Get a user by their email address.
     *
     * @param string $email The email address to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByEmail(string $email): ?array
    {
        return $this->getUserAccountService()->getUserByEmail($email);
    }

    public function getUserByAppleId(string $appleId): ?array
    {
        return $this->getUserAccountService()->getUserByAppleId($appleId);
    }

    public function getUserByDiscordId(string $discordId): ?array
    {
        return $this->getUserAccountService()->getUserByDiscordId($discordId);
    }

    /**
     * Check if a user with the given email exists.
     *
     * @param string $email The email address to check
     *
     * @return bool True if a user with this email exists
     */
    public function userExistsByEmail(string $email): bool
    {
        return $this->getUserAccountService()->userExistsByEmail($email);
    }

    /**
     * Check if a user with the given username exists.
     *
     * @param string $username The username to check
     *
     * @return bool True if a user with this username exists
     */
    public function userExistsByUsername(string $username): bool
    {
        return $this->getUserAccountService()->userExistsByUsername($username);
    }

    public function validateUserCredentials($user, array $credentials): bool
    {
        return $this->getUserAccountService()->validateUserCredentials($user, $credentials);
    }

    /**
     * Get a user by their username.
     *
     * @param string $username The username to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->getUserAccountService()->getUserByUsername($username);
    }

    public function updateRememberToken(string $identifier, string $token): void
    {
        $this->getUserAccountService()->updateRememberToken($identifier, $token);
    }

    public function getMessages(?string $userId = null, bool $includeAcknowledged = false, int $limit = 100): array
    {
        return $this->getWorkflowMessagingService()->getMessages($userId, $includeAcknowledged, $limit);
    }

    public function getUsersForMessaging(): array
    {
        return $this->getWorkflowMessagingService()->getUsersForMessaging();
    }

    /**
     * Get all users in the system.
     *
     * @return array List of all users
     */
    public function getAllUsers(): array
    {
        return $this->getAdminMaintenanceService()->getAllUsers();
    }

    /**
     * Get user activity data (progress, badges, reviews, etc.)
     *
     * @param string $userId
     * @return array
     */
    /**
     * Get user activity data (progress, badges, reviews, etc.)
     *
     * @param string $userId
     * @return array
     */
    public function getUserActivityData(string $userId): array
    {
        return $this->getUserActivityService()->getUserActivityData($userId);
    }

    /**
     * Update a user's book queue with a new list of book IDs.
     *
     * @param string $userId The user ID
     * @param array $bookIds List of book IDs for the queue
     *
     * @return bool Success status
     */
    public function updateBookQueue(string $userId, array $bookIds): bool
    {
        return $this->getUserLibraryStateService()->updateBookQueue($userId, $bookIds);
    }

    public function getBookmarks(string $userId, string $title, string $author): array
    {
        return $this->getUserLibraryStateService()->getBookmarks($userId, $title, $author);
    }

    public function getBookmark(string $bookmarkId, string $userId, string $title, string $author): ?array
    {
        return $this->getUserLibraryStateService()->getBookmark($bookmarkId, $userId, $title, $author);
    }

    public function createBookmark(array $data): string
    {
        return $this->getUserLibraryStateService()->createBookmark($data);
    }

    public function updateBookmark(string $bookmarkId, array $data): bool
    {
        return $this->getUserLibraryStateService()->updateBookmark($bookmarkId, $data);
    }

    public function deleteBookmark(string $bookmarkId, string $userId, string $title, string $author): bool
    {
        return $this->getUserLibraryStateService()->deleteBookmark($bookmarkId, $userId, $title, $author);
    }

    public function deleteBookmarkById(string $bookmarkId, string $userId): bool
    {
        return $this->getUserLibraryStateService()->deleteBookmarkById($bookmarkId, $userId);
    }

    // EXTERNAL READS / PREVIOUSLY READ
    public function getExternalReads(string $userId, string $bookId): array
    {
        return $this->getUserLibraryStateService()->getExternalReads($userId, $bookId);
    }

    public function getExternalRead(string $externalReadId, string $userId, string $bookId): ?array
    {
        return $this->getUserLibraryStateService()->getExternalRead($externalReadId, $userId, $bookId);
    }

    public function createExternalRead(array $data): string
    {
        return $this->getUserLibraryStateService()->createExternalRead($data);
    }

    public function updateExternalRead(string $externalReadId, array $data): bool
    {
        return $this->getUserLibraryStateService()->updateExternalRead($externalReadId, $data);
    }

    public function deleteExternalRead(string $externalReadId, string $userId, string $bookId): bool
    {
        return $this->getUserLibraryStateService()->deleteExternalRead($externalReadId, $userId, $bookId);
    }

    public function getDocument(string $collection, string $docId): ?array
    {
        return $this->getGenericDocumentService()->getDocument($collection, $docId);
    }

    public function updateDocument(string $collection, string $id, array $data): bool
    {
        return $this->getGenericDocumentService()->updateDocument($collection, $id, $data);
    }

    /**
     * Create an API token for a user.
     *
     * @param array $tokenData the token data including user_id, token, etc
     *
     * @return string|null The token ID or null on failure
     */
    public function createApiToken(array $tokenData): ?string
    {
        return $this->getTokenMaintenanceService()->createApiToken($tokenData);
    }

    public function getApiTokenByValue(string $tokenValue): ?array
    {
        return $this->getTokenMaintenanceService()->getApiTokenByValue($tokenValue);
    }

    /**
     * Delete an API token by its value.
     *
     * @param string $tokenValue The token value to delete
     *
     * @return bool True if token was deleted, false otherwise
     */
    public function deleteApiTokenByValue(string $tokenValue): bool
    {
        return $this->getTokenMaintenanceService()->deleteApiTokenByValue($tokenValue);
    }

    /**
     * Get pending account requests.
     *
     * @return array List of pending account requests
     */
    public function getPendingAccountRequests(): array
    {
        return $this->getUserAccountService()->getPendingAccountRequests();
    }

    /**
     * Get a specific account request by ID.
     *
     * @param string $id The account request ID
     *
     * @return array|null The account request data or null if not found
     */
    public function getAccountRequest(string $id): ?array
    {
        return $this->getUserAccountService()->getAccountRequest($id);
    }

    /**
     * Approve an account request.
     *
     * @param string $id The account request ID
     *
     * @return bool True if the request was approved successfully
     */
    public function approveAccountRequest(string $id): bool
    {
        return $this->getUserAccountService()->approveAccountRequest($id);
    }

    /**
     * Reject an account request.
     *
     * @param string $id The account request ID
     *
     * @return bool True if the request was rejected successfully
     */
    public function rejectAccountRequest(string $id): bool
    {
        return $this->getUserAccountService()->rejectAccountRequest($id);
    }

    public function createMessage(array $messageData): ?string
    {
        return $this->getWorkflowMessagingService()->createMessage($messageData);
    }

    public function acknowledgeMessage(string $messageId): bool
    {
        return $this->getWorkflowMessagingService()->acknowledgeMessage($messageId);
    }

    public function createJob(array $data)
    {
        return $this->getWorkflowMessagingService()->createJob($data);
    }

    public function deleteMessage(string $messageId): bool
    {
        return $this->getAdminMaintenanceService()->deleteMessage($messageId);
    }

    /**
     * @inheritDoc
     */
    public function recordReadingSession(string $userId, string $bookId, array $data): array
    {
        return $this->getUserReadingStatsService()->recordReadingSession($userId, $bookId, $data);
    }

    /**
     * @inheritDoc
     */
    public function getDailyStats(string $userId, ?string $from = null, ?string $to = null): array
    {
        return $this->getUserReadingStatsService()->getDailyStats($userId, $from, $to);
    }

    /**
     * @inheritDoc
     */
    public function getBookStats(string $userId, string $bookId): array
    {
        return $this->getUserReadingStatsService()->getBookStats($userId, $bookId);
    }

    /**
     * @inheritDoc
     */
    public function getUserStats(string $userId): array
    {
        return $this->getUserReadingStatsService()->getUserStats($userId);
    }

    /**
     * @inheritDoc
     */
    public function getStreaks(string $userId): array
    {
        return $this->getUserReadingStatsService()->getStreaks($userId);
    }
}
