# AbLibrarian Lite

A minimal self-hosted sync and stats server for the [AbLibrarian](https://ablibrarian.com) audiobook app. It provides listening history sync, statistics, bookmarks, and user management without requiring a full book library server.

## Capabilities

| Capability | Description |
|---|---|
| `HISTORY_SYNC` | Bidirectional sync of listening events across devices |
| `STATS` | Listening statistics, streaks, and reading history |
| `BOOKMARKS_SYNC` | Sync bookmarks and reading positions across devices |

Additional user features: progress tracking, badges, recommendations, messages, reading goals, and external read history.

## Quick Start (Docker, local-only)

```bash
# Optional for a remote deployment: copy this file and set APP_URL to your HTTPS URL.
cp .env.example .env
docker compose up -d
```

The server will be available at `http://localhost:8080` on the Docker host only.
This is for same-machine development and health checks, not for connecting the mobile app.

On first run, migrations run automatically and the database is created at `./data/database.sqlite`.

## Docker Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | (empty) | Laravel app key — generated on first start if empty |
| `APP_ENV` | `production` | Environment (`production` or `local`) |
| `APP_DEBUG` | `false` | Enable debug mode |
| `APP_URL` | `https://server.example.com` | Public HTTPS URL of the server |
| `DB_CONNECTION` | `sqlite` | Database driver (`sqlite` or `mysql`) |
| `DB_DATABASE` | `/app/storage/database.sqlite` | SQLite database path |
| `DB_HOST` | `127.0.0.1` | MySQL host (when using MySQL) |
| `DB_PORT` | `3306` | MySQL port |
| `DB_USERNAME` | `lite` | MySQL username |
| `DB_PASSWORD` | (empty) | MySQL password |
| `LITE_REGISTRATION_OPEN` | `true` | Allow new user registration |
| `LITE_OTP_ENABLED` | `false` | Enable email OTP login |
| `MAIL_MAILER` | `log` | Mail driver for OTP and password reset emails |

## Bare-Metal Setup

Requirements: PHP 8.3+, Composer, SQLite or MySQL

```bash
# Clone and install
git clone https://github.com/generation-i-systems/audiobook-librarian-lite
cd audiobook-librarian-lite
composer install --no-dev --optimize-autoloader

# Configure
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Serve locally only; use a TLS reverse proxy for device access
php artisan serve --port=8080
```

## Using MySQL Instead of SQLite

In `.env` (or docker environment):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=librarian_lite
DB_USERNAME=lite
DB_PASSWORD=secret
```

## Connecting from the AbLibrarian App

1. Open the AbLibrarian app
2. Go to **Settings → Connected Services**
3. Tap **AbLibrarian Lite**
4. Enter your server URL (e.g. `https://library.example.com`)
5. Register or log in

## HTTPS requirement for app connections

Use HTTPS with a valid certificate for every Lite server configured in the mobile app.
The app intentionally does not support arbitrary cleartext `http://` servers. The Docker
port binds to `127.0.0.1` by default; put Caddy, nginx, Traefik, or another TLS-terminating
reverse proxy in front of it, set `APP_URL=https://library.example.com`, and proxy to
`http://127.0.0.1:8080`. Do not expose the container's HTTP port directly to user devices.

For a managed HTTPS endpoint, set matching `APP_URL` and `PUBLIC_HOST` values in `.env` and run:

```bash
docker compose --profile https up -d --build
```

The included Caddy profile obtains and renews the certificate. See
[cross-platform installation](docs/INSTALLATION.md) for Linux, macOS, and Windows guidance.

## Admin User Management

Admins can manage users via the API at `POST /api/v1/admin/users`. To create the first admin user:

```bash
php artisan app:create-admin-user
```

Or register normally and then set `is_admin = 1` directly in the database.
