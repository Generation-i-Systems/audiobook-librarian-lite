# Lite API

The versioned API is rooted at `/api/v1`. Authenticated clients synchronize listening events,
positions, bookmarks, tags, goals, friends, statistics, and achievements.

## Authentication and users

- `POST /register` creates an account pending administrator approval.
- `POST /login` returns a bearer token after approval.
- `GET /user` returns the authenticated user.
- `POST /logout` invalidates the token.

Administrators manage approval and roles through the web interface at `/admin/users` or the
admin-user API endpoints.

## Device and event sync

Every sync request includes `X-Device-ID`; position writes also require `X-Device-Name`.

- `POST /sync/events` pushes events and retrieves remote events.
- `GET|POST /sync/positions` retrieves or writes canonical per-book positions.
- `GET /sync/positions/show` retrieves all known positions for one title and author.
- `GET|PUT|DELETE /devices` manages a user's registered devices.

## Statistics and achievements

- `GET /statistics/overview`, `/statistics/daily`, and `/statistics/reading-history` provide
  event-derived statistics.
- `GET /reading-stats/daily`, `/reading-stats/user`, and `/reading-stats/streaks` provide compact
  client statistics.
- `GET /badges`, `/badges/user`, and `/badges/unnotified` retrieve achievement state;
  `POST /badges/mark-notified` acknowledges displayed achievements.

## Bookmarks, tags, goals, and friends

- `GET|POST|DELETE /bookmarks` manages individual bookmarks. Device-aware sync is available at
  `GET|POST|DELETE /sync/bookmarks`.
- `GET|PUT /books/tags` retrieves or updates tags for a title and author; `GET /tags/popular`
  lists system tags.
- `GET|POST /goals/listening`, plus its history, update, delete, and breakdown endpoints, manages
  listening goals.
- `/friends` provides friend listing/removal, QR invitations, and email invitation workflows.

Administrators can manage groups at `/admin/groups` and users at `/admin/users`. The authenticated
`GET /user` response includes the user's groups.

The machine-readable OpenAPI document is served at `/api/v1/openapi.json`.
