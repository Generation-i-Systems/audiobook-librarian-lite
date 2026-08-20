# AbLibrarian Lite

AbLibrarian Lite is a self-hosted companion service for the
[AbLibrarian](https://www.ablibrarian.com/) app. Its purpose is deliberately narrow:

- sync listening events and the latest playback position across a user's devices;
- calculate listening statistics and streaks from those events; and
- award and synchronize listening achievements (badges); and
- synchronize bookmarks, reading tags, listening goals, and friend connections.

It does not host audiobook files or catalogue metadata, import media, provide recommendations,
messaging, AI enrichment, skins, or themes.

## Quick start

```bash
cp .env.example .env
docker compose up -d
```

This starts a local-only service at `http://localhost:8080`. For a mobile-app connection, use a
public HTTPS endpoint as described in [docs/INSTALLATION.md](docs/INSTALLATION.md).

The first start creates the configured SQLite database. MySQL is also supported; configure the
standard `DB_*` values in `.env` before starting the service.

## Connect the app

1. Open AbLibrarian and choose **Settings → Connected Services → AbLibrarian Lite**.
2. Enter this server's HTTPS URL.
3. Register an account, then wait for an administrator to approve it as trial or full access.
4. Sign in and allow the app to synchronize events, positions, bookmarks, tags, goals, friends,
   statistics, and achievements.

## Administration

Create the initial administrator with:

```bash
php artisan app:create-admin-user
```

Administrators sign in at `/admin/login` to approve registrations, assign access roles, and manage
users. Each user record includes the available groups, friends, invitations, bookmarks, goals,
achievements, positions, and book-status data. The application API is documented in
[docs/API.md](docs/API.md), with the machine-readable specification at `/api/v1/openapi.json`.
Trial and full users currently have the same permissions; the distinction is retained for a future
trial-expiration job.

## Development

```bash
composer test
npm test
```

Tests are isolated to in-memory SQLite. Never run destructive migration commands against a real
Lite database.
