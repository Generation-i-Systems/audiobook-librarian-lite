<?php

declare(strict_types=1);

namespace App\Contracts;

interface DocumentStoreServiceInterface
{
    // BOOKS
    public function getBook(string $id, ?int $userId = null);

    /**
     * List books with pagination and optional filtering.
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Number of items per page
     * @param array $filters Optional filters (e.g., ['author' => 'John Doe', 'genre' => 'Fiction'])
     * @param bool $withRelated Whether to load related data (authors, series)
     * @param string $sort Field to sort by
     * @param string $order Direction to sort
     * @param bool $includeAllBooks Whether to include books without files
     * @param int|null $userId Optional user ID for personal data (progress, etc)
     *
     * @return array [
     *               'data' => array,  // The paginated list of books
     *               'total' => int,   // Total number of books matching filters
     *               'per_page' => int,// Number of items per page
     *               'current_page' => int, // Current page number
     *               'last_page' => int,    // Last available page number
     *               ]
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
    ): array;

    // USERS
    public function getUserById($identifier);

    public function getUserByCredentials($credentials);

    public function getUserByRememberToken($identifier, $token);

    /**
     * Validate a user's credentials.
     *
     * @param mixed $user The user object
     * @param array $credentials The credentials to validate
     *
     * @return bool
     */
    public function validateUserCredentials($user, array $credentials): bool;

    /**
     * Get a user by their username.
     *
     * @param string $username The username to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByUsername(string $username): ?array;

    /**
     * Get a user by their email address.
     *
     * @param string $email The email address to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByEmail(string $email): ?array;

    /**
     * Get a user by their Apple ID.
     *
     * @param string $appleId The Apple ID (sub claim) to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByAppleId(string $appleId): ?array;

    /**
     * Get a user by their Discord ID.
     *
     * @param string $discordId The Discord ID to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByDiscordId(string $discordId): ?array;

    /**
     * Check if a user with the given email exists.
     *
     * @param string $email The email address to check
     *
     * @return bool True if a user with this email exists
     */
    public function userExistsByEmail(string $email): bool;

    /**
     * Check if a user with the given username exists.
     *
     * @param string $username The username to check
     *
     * @return bool True if a user with this username exists
     */
    public function userExistsByUsername(string $username): bool;

    public function createUser(array $data);

    public function updateUser(string $id, array $data);

    public function deleteUser(string $id);

    public function getUsersForMessaging(): array;

    /**
     * Update the "remember me" token for the given user.
     *
     * @param string $identifier The user's identifier
     * @param string $token The new remember token
     */
    public function updateRememberToken(string $identifier, string $token): void;

    /**
     * Get all users in the system.
     *
     * @return array List of all users
     */
    public function getAllUsers(): array;

    /**
     * Get user activity data (progress, badges, reviews, etc.)
     *
     * @param string $userId
     * @return array
     */
    public function getUserActivityData(string $userId): array;

    // MESSAGES
    public function createMessage(array $messageData): ?string;

    /**
     * Mark a message as acknowledged.
     */
    public function acknowledgeMessage(string $messageId): bool;

    /**
     * Create an API token for a user.
     *
     * @param array $tokenData the token data including user_id, token, etc
     *
     * @return string|null The token ID or null on failure
     */
    public function createApiToken(array $tokenData): ?string;

    /**
     * Get an API token by its raw value.
     *
     * @return array|null Token row as an array (e.g. ['id' => int, 'user_id' => int|string, 'token' => string])
     */
    public function getApiTokenByValue(string $tokenValue): ?array;

    /**
     * Delete an API token by its value.
     *
     * @param string $tokenValue The token value to delete
     *
     * @return bool True if token was deleted, false otherwise
     */
    public function deleteApiTokenByValue(string $tokenValue): bool;

    public function getMessages(?string $userId = null, bool $includeAcknowledged = false, int $limit = 100): array;

    /**
     * Update a document in a specific collection.
     *
     * @param string $collection the collection name
     * @param string $id the document ID
     * @param array $data the data to update
     *
     * @return bool true on success, false on failure
     */
    public function updateDocument(string $collection, string $id, array $data): bool;

    // BOOKMARKS
    /**
     * Get all bookmarks for a user and book.
     */
    public function getBookmarks(string $userId, string $title, string $author): array;

    /**
     * Get a specific bookmark by ID, filtered by user and book.
     */
    public function getBookmark(string $bookmarkId, string $userId, string $title, string $author): ?array;

    /**
     * Create a new bookmark.
     *
     * @return string Bookmark ID
     */
    public function createBookmark(array $data): string;

    /**
     * Update a bookmark.
     */
    public function updateBookmark(string $bookmarkId, array $data): bool;

    /**
     * Delete a bookmark.
     */
    public function deleteBookmark(string $bookmarkId, string $userId, string $title, string $author): bool;

    /**
     * Delete a bookmark by ID (without book context).
     */
    public function deleteBookmarkById(string $bookmarkId, string $userId): bool;

    // EXTERNAL READS
    /**
     * Get all external/previously-read entries for a user and book.
     *
     * @param string $userId The user ID
     * @param string $bookId The book ID
     *
     * @return array List of external read entries
     */
    public function getExternalReads(string $userId, string $bookId): array;

    /**
     * Get a specific external read entry.
     *
     * @param string $externalReadId The external read ID
     * @param string $userId The user ID
     * @param string $bookId The book ID
     *
     * @return array|null The external read data or null if not found
     */
    public function getExternalRead(string $externalReadId, string $userId, string $bookId): ?array;

    /**
     * Create a new external read entry.
     *
     * @param array $data The external read data
     *
     * @return string The created external read ID
     */
    public function createExternalRead(array $data): string;

    /**
     * Update an external read entry.
     *
     * @param string $externalReadId The external read ID
     * @param array $data The updated data
     *
     * @return bool Success status
     */
    public function updateExternalRead(string $externalReadId, array $data): bool;

    /**
     * Delete an external read entry.
     *
     * @param string $externalReadId The external read ID
     * @param string $userId The user ID
     * @param string $bookId The book ID
     *
     * @return bool Success status
     */
    public function deleteExternalRead(string $externalReadId, string $userId, string $bookId): bool;

    // ACCOUNT REQUESTS

    /**
     * Get pending account requests.
     *
     * @return array List of pending account requests
     */
    public function getPendingAccountRequests(): array;

    /**
     * Get a specific account request by ID.
     *
     * @param string $id The account request ID
     *
     * @return array|null The account request data or null if not found
     */
    public function getAccountRequest(string $id): ?array;

    /**
     * Approve an account request.
     *
     * @param string $id The account request ID
     *
     * @return bool True if the request was approved successfully
     */
    public function approveAccountRequest(string $id): bool;

    /**
     * Reject an account request.
     *
     * @param string $id The account request ID
     *
     * @return bool True if the request was rejected successfully
     */
    public function rejectAccountRequest(string $id): bool;

    // GENERIC

    public function getDocument(string $collection, string $docId): ?array;
}
