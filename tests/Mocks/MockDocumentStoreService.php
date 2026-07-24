<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\DocumentStoreServiceInterface;

class MockDocumentStoreService implements DocumentStoreServiceInterface
{
    protected $books = [];

    protected $series = [];

    protected $genres = [];

    protected $authors = [];

    protected $users = [];

    protected $messages = [];

    protected $jobs = [];

    protected $queues = [];

    protected $readingProgress = [];

    protected $narrators = [];

    protected $apiTokens = [];

    protected $accountRequests = [];

    protected $follows = [];

    protected array $libraryRepairIssues = [];

    protected int $libraryRepairIssueAutoIncrement = 1;

    public function validateUserCredentials($user, array $credentials): bool
    {
        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! $email || ! $password) {
            return false;
        }

        $user = collect($this->users)->firstWhere('email', $email);

        if (! $user) {
            return false;
        }

        return password_verify($password, $user['password'] ?? '');
    }

    public function updateRememberToken(string $identifier, string $token): void
    {
        $this->users = array_map(function ($u) use ($identifier, $token) {
            if ($u['id'] === $identifier || $u['email'] === $identifier) {
                $u['remember_token'] = $token;
            }

            return $u;
        }, $this->users);
    }

    public function getBook(string $id, ?int $userId = null)
    {
        return $this->books[$id] ?? null;
    }

    public function searchBooks($query, $limit = 10, $offset = 0)
    {
        $results = [];

        foreach ($this->books as $book) {
            if (
                stripos($book['title'] ?? '', $query) !== false ||
                stripos($book['author'] ?? '', $query) !== false
            ) {
                $results[] = $book;
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return array_slice($results, $offset, $limit);
    }

    public function getBookByPath($path)
    {
        foreach ($this->books as $book) {
            if (($book['path'] ?? '') === $path) {
                return $book;
            }
        }

        return null;
    }

    public function getBooksByIds(array $ids)
    {
        $results = [];

        foreach ($ids as $id) {
            if (isset($this->books[$id])) {
                $results[] = $this->books[$id];
            }
        }

        return $results;
    }

    public function getBooksBySeries($seriesName)
    {
        $results = [];

        foreach ($this->books as $book) {
            if (isset($book['series']) && is_array($book['series'])) {
                foreach ($book['series'] as $series) {
                    if (isset($series['seriesName']) && $series['seriesName'] === $seriesName) {
                        $results[] = $book;
                        break;
                    }
                }
            }
        }

        return $results;
    }

    public function getBooksByAuthor($author)
    {
        $results = [];

        foreach ($this->books as $book) {
            if (isset($book['author']) && $book['author'] === $author) {
                $results[] = $book;
            }
        }

        return $results;
    }

    public function getBooksByGenre($genre)
    {
        $results = [];

        foreach ($this->books as $book) {
            if (isset($book['genres']) && in_array($genre, $book['genres'])) {
                $results[] = $book;
            }
        }

        return $results;
    }

    /**
     * @inheritdoc
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
        // Validate order direction
        $order = in_array(strtolower($order), ['asc', 'desc']) ? strtolower($order) : 'asc';
        // Apply filters
        $filteredBooks = array_filter($this->books, function ($book) use ($filters) {
            // Filter by author
            if (! empty($filters['author'])) {
                $authorMatch = false;
                $authors = is_array($book['author'] ?? null) ? $book['author'] : [$book['author'] ?? ''];

                foreach ($authors as $author) {
                    $authorName = is_array($author) ? ($author['name'] ?? '') : $author;

                    if (stripos($authorName, $filters['author']) !== false) {
                        $authorMatch = true;
                        break;
                    }
                }

                if (! $authorMatch) {
                    return false;
                }
            }

            // Filter by genre
            if (! empty($filters['genre'])) {
                $genres = is_array($book['genre'] ?? null) ? $book['genre'] : [$book['genre'] ?? ''];

                if (! in_array($filters['genre'], $genres, true)) {
                    return false;
                }
            }

            // Filter by series
            if (! empty($filters['series'])) {
                $seriesMatch = false;
                $seriesList = is_array($book['series'] ?? null) ? $book['series'] : ($book['series'] ? [$book['series']] : []);

                foreach ($seriesList as $series) {
                    $seriesName = '';

                    if (is_array($series)) {
                        $seriesName = $series['seriesName'] ?? $series['name'] ?? '';
                    } elseif (is_string($series)) {
                        $seriesName = $series;
                    }

                    if (stripos($seriesName, $filters['series']) !== false) {
                        $seriesMatch = true;
                        break;
                    }
                }

                if (! $seriesMatch) {
                    return false;
                }
            }

            return true;
        });

        // Get total count before pagination
        $total = count($filteredBooks);

        // Calculate pagination
        $offset = ($page - 1) * $perPage;
        $paginatedBooks = array_slice($filteredBooks, $offset, $perPage);

        // Ensure all book fields are properly formatted
        $result = [];

        foreach ($paginatedBooks as $book) {
            $formattedBook = $this->ensureBookFields($book);

            // Load related data if requested
            if ($withRelated) {
                $formattedBook = $this->loadRelatedData($formattedBook);
            }

            $result[] = $formattedBook;
        }

        return [
            'data' => $result,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * Ensure all required book fields are present and properly formatted.
     *
     * @param array $book
     *
     * @return array
     */
    protected function ensureBookFields(array $book): array
    {
        $defaults = [
            'id' => uniqid(),
            'title' => 'Untitled',
            'author' => [],
            'series' => [],
            'genre' => [],
            'description' => '',
            'cover_image' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $book = array_merge($defaults, $book);

        // Ensure author is an array of arrays with name
        if (! empty($book['author'])) {
            $authors = is_array($book['author']) ? $book['author'] : [$book['author']];
            $book['author'] = array_map(function ($author) {
                if (is_array($author) && isset($author['name'])) {
                    return $author;
                }

                return ['name' => (string) $author];
            }, $authors);
        }

        // Ensure series is an array of arrays with seriesName
        if (! empty($book['series'])) {
            $seriesList = is_array($book['series']) ? $book['series'] : [$book['series']];
            $book['series'] = array_map(function ($series) {
                if (is_array($series)) {
                    // Convert 'name' to 'seriesName' if needed
                    if (isset($series['name']) && ! isset($series['seriesName'])) {
                        $series['seriesName'] = $series['name'];
                        unset($series['name']);
                    }

                    return $series;
                }

                return ['seriesName' => (string) $series];
            }, $seriesList);
        }

        // Ensure genre is an array of strings
        if (! empty($book['genre'])) {
            $book['genre'] = is_array($book['genre']) ? $book['genre'] : [$book['genre']];
        }

        return $book;
    }

    /**
     * Load related data for a book.
     *
     * @param array $book
     *
     * @return array
     */
    protected function loadRelatedData(array $book): array
    {
        // In a real implementation, this would load related data from the database
        // For the mock, we'll just ensure the structure is correct

        // Ensure authors have all required fields
        if (! empty($book['author'])) {
            $book['authors'] = $book['author'];
            unset($book['author']);
        }

        // Ensure series have all required fields
        if (! empty($book['series'])) {
            $book['series'] = array_map(function ($series) {
                if (is_array($series)) {
                    return [
                        'seriesName' => $series['seriesName'] ?? $series['name'] ?? 'Unknown Series',
                        'position' => $series['position'] ?? null,
                    ];
                }

                return ['seriesName' => (string) $series];
            }, $book['series']);
        }

        return $book;
    }

    // dumpAllBooks method already exists above

    public function getUserById($identifier)
    {
        return $this->users[$identifier] ?? null;
    }

    public function getUserByCredentials($credentials)
    {
        foreach ($this->users as $user) {
            if (isset($user['email']) && $user['email'] === ($credentials['email'] ?? '')) {
                return $user;
            }
        }

        return null;
    }

    public function getUserByRememberToken($identifier, $token)
    {
        $user = $this->getUserById($identifier);

        if ($user && isset($user['remember_token']) && $user['remember_token'] === $token) {
            return $user;
        }

        return null;
    }

    public function createUser(array $data)
    {
        $id = $data['id'] ?? uniqid('user_');
        $data['id'] = $id;
        $this->users[$id] = $data;

        return $id;
    }

    public function updateUser(string $id, array $data)
    {
        if (! isset($this->users[$id])) {
            return false;
        }

        $this->users[$id] = array_merge($this->users[$id], $data);

        return true;
    }

    public function deleteUser(string $id)
    {
        if (! isset($this->users[$id])) {
            return false;
        }

        unset($this->users[$id]);

        return true;
    }

    /**
     * Get a user by their Apple ID.
     *
     * @param string $appleId The Apple ID (sub claim) to search for
     *
     * @return array|null The user data or null if not found
     */
    public function getUserByAppleId(string $appleId): ?array
    {
        foreach ($this->users as $user) {
            if (isset($user['apple_id']) && $user['apple_id'] === $appleId) {
                return $user;
            }
        }

        return null;
    }

    public function getUserByDiscordId(string $discordId): ?array
    {
        foreach ($this->users as $user) {
            if (isset($user['discord_id']) && $user['discord_id'] === $discordId) {
                return $user;
            }
        }

        return null;
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
        foreach ($this->users as $user) {
            if (isset($user['email']) && $user['email'] === $email) {
                return $user;
            }
        }

        return null;
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
        foreach ($this->users as $user) {
            if (isset($user['email']) && $user['email'] === $email) {
                return true;
            }
        }

        return false;
    }

    public function getExternalReads(string $userId, string $bookId): array
    {
        return [];
    }

    public function getExternalRead(string $externalReadId, string $userId, string $bookId): ?array
    {
        return null;
    }

    public function createExternalRead(array $data): string
    {
        return '1';
    }

    public function updateExternalRead(string $externalReadId, array $data): bool
    {
        return true;
    }

    public function deleteExternalRead(string $externalReadId, string $userId, string $bookId): bool
    {
        return true;
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
        foreach ($this->users as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                return true;
            }
        }

        return false;
    }

    public function addLibraryRepairIssue(array $issue): array
    {
        $issue = $this->ensureLibraryRepairIssueFields($issue);
        $this->libraryRepairIssues[$issue['id']] = $issue;

        return $issue;
    }

    protected function ensureLibraryRepairIssueFields(array $issue): array
    {
        $issue['id'] ??= $this->libraryRepairIssueAutoIncrement++;
        $issue['issueType'] ??= 'missing_directory';
        $issue['status'] ??= 'pending';
        $issue['directoryPath'] ??= null;
        $issue['metadata'] ??= [];
        $issue['autoResolved'] = (bool) ($issue['autoResolved'] ?? false);
        $issue['createdAt'] ??= now()->toIso8601String();
        $issue['updatedAt'] ??= $issue['createdAt'];
        $issue['resolvedAt'] ??= null;
        $issue['resolutionNotes'] ??= null;
        $issue['book'] ??= null;

        return $issue;
    }

    protected function filterLibraryRepairIssues(array $issues, array $filters): array
    {
        return array_values(array_filter($issues, function ($issue) use ($filters) {
            if (! empty($filters['issue_type']) && $issue['issueType'] !== $filters['issue_type']) {
                return false;
            }

            if (! empty($filters['status']) && $issue['status'] !== $filters['status']) {
                return false;
            }

            if (array_key_exists('auto_resolved', $filters) && $filters['auto_resolved'] !== '') {
                if ((bool) $issue['autoResolved'] !== (bool) $filters['auto_resolved']) {
                    return false;
                }
            }

            if (! empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $directoryMatches = is_string($issue['directoryPath']) && str_contains(strtolower($issue['directoryPath']), $search);
                $bookMatches = ! empty($issue['book']['title']) && str_contains(strtolower($issue['book']['title']), $search);

                if (! $directoryMatches && ! $bookMatches) {
                    return false;
                }
            }

            return true;
        }));
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
        foreach ($this->users as $user) {
            if (isset($user['username']) && $user['username'] === $username) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Get all users in the system.
     *
     * @return array List of all users
     */
    public function getAllUsers(): array
    {
        $allUsers = [];

        foreach ($this->users as $id => $user) {
            $userData = $user;
            $userData['id'] = $id;
            $allUsers[] = $userData;
        }

        return $allUsers;
    }

    public function getUsersForMessaging(): array
    {
        return array_values($this->users);
    }

    public function createMessage(array $messageData): ?string
    {
        $id = $messageData['id'] ?? uniqid('message_');
        $messageData['id'] = $id;
        $this->messages[$id] = $messageData;

        return $id;
    }

    public function acknowledgeMessage(string $messageId): bool
    {
        if (! isset($this->messages[$messageId])) {
            return false;
        }

        $this->messages[$messageId]['acknowledged_at'] = now()->toIso8601String();

        return true;
    }

    public function getMessages(?string $userId = null, bool $includeAcknowledged = false, int $limit = 100): array
    {
        if ($userId === null) {
            return array_slice(array_values($this->messages), 0, $limit);
        }

        $results = [];

        foreach ($this->messages as $message) {
            if (isset($message['userId']) && $message['userId'] === $userId) {
                if ($includeAcknowledged || ! ($message['acknowledged'] ?? false)) {
                    $results[] = $message;
                }
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function getDocument(string $collection, string $docId): ?array
    {
        $collections = [
            'books' => $this->books,
            'users' => $this->users,
            'series' => $this->series,
            'genres' => $this->genres,
            'authors' => $this->authors,
            'messages' => $this->messages,
            'jobs' => $this->jobs,
        ];

        return $collections[$collection][$docId] ?? null;
    }

    public function addSeries($name)
    {
        $id = uniqid('series_');
        $this->series[$id] = ['id' => $id, 'seriesName' => $name];

        return $id;
    }

    public function addAuthor($name)
    {
        $id = uniqid('author_');
        $this->authors[$id] = ['id' => $id, 'name' => $name];

        return $id;
    }

    public function getBookmarks(string $userId, string $bookId): array
    {
        return [];
    }

    public function getBookmark(string $bookmarkId, string $userId, string $bookId): ?array
    {
        return null;
    }

    public function createBookmark(array $data): string
    {
        $id = uniqid('bookmark_');

        return $id;
    }

    public function updateBookmark(string $bookmarkId, array $data): bool
    {
        return true;
    }

    public function deleteBookmark(string $bookmarkId, string $userId, string $bookId): bool
    {
        return true;
    }

    public function deleteBookmarkById(string $bookmarkId, string $userId): bool
    {
        return true;
    }

    public function updateBookQueue(string $userId, array $bookIds): void
    {
        $this->queues[$userId] = $bookIds;
    }

    /**
     * @inheritDoc
     */
    public function getFollowers(string $followableType, string $followableId): array
    {
        $followers = [];
        $target = ['type' => $followableType, 'id' => $followableId];

        foreach ($this->follows as $followerId => $follows) {
            if (in_array($target, $follows, true)) {
                $followers[] = $followerId;
            }
        }

        return $followers;
    }

    /**
     * @inheritDoc
     */
    public function getFollowing(string $userId, string $followableType = null): array
    {
        if (! isset($this->follows[$userId])) {
            return [];
        }

        if ($followableType === null) {
            return $this->follows[$userId];
        }

        return array_filter(
            $this->follows[$userId],
            fn ($follow) => $follow['type'] === $followableType
        );
    }

    /**
     * @inheritDoc
     */
    public function updateDocument(string $collection, string $id, array $data): bool
    {
        $collection = strtolower($collection);

        if (! property_exists($this, $collection) || ! is_array($this->$collection)) {
            return false;
        }

        if (! isset($this->{$collection}[$id])) {
            return false;
        }

        $this->{$collection}[$id] = array_merge($this->{$collection}[$id], $data);

        return true;
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
        $id = uniqid('token_');
        $tokenData['id'] = $id;
        $this->apiTokens[$id] = $tokenData;

        return $id;
    }

    public function getApiTokenByValue(string $tokenValue): ?array
    {
        foreach ($this->apiTokens as $token) {
            if (isset($token['token']) && $token['token'] === $tokenValue) {
                return $token;
            }
        }

        return null;
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
        foreach ($this->apiTokens as $id => $token) {
            if (isset($token['token']) && $token['token'] === $tokenValue) {
                unset($this->apiTokens[$id]);

                return true;
            }
        }

        return false;
    }

    /**
     * Get pending account requests.
     *
     * @return array List of pending account requests
     */
    public function getPendingAccountRequests(): array
    {
        $pendingRequests = [];

        foreach ($this->accountRequests as $id => $request) {
            if (isset($request['status']) && $request['status'] === 'pending') {
                $pendingRequests[$id] = $request;
            }
        }

        return array_values($pendingRequests);
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
        return $this->accountRequests[$id] ?? null;
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
        if (! isset($this->accountRequests[$id])) {
            return false;
        }

        // Update status to approved
        $this->accountRequests[$id]['status'] = 'approved';

        // Create a user from the account request data
        $userData = [
            'name' => $this->accountRequests[$id]['name'] ?? '',
            'email' => $this->accountRequests[$id]['email'] ?? '',
            'username' => $this->accountRequests[$id]['username'] ?? '',
            'password' => $this->accountRequests[$id]['password'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->createUser($userData);

        return true;
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
        if (! isset($this->accountRequests[$id])) {
            return false;
        }

        // Update status to rejected
        $this->accountRequests[$id]['status'] = 'rejected';

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getUserActivityData(string $userId): array
    {
        return [];
    }
}
