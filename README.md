# AbLibrarian Lite

A minimal self-hosted sync and stats server for the [AbLibrarian](https://ablibrarian.com) audiobook app. It provides listening history sync, statistics, bookmarks, and user management without requiring a full book library server.

## Capabilities

| Capability | Description |
|---|---|
| `HISTORY_SYNC` | Bidirectional sync of listening events across devices |
| `STATS` | Listening statistics, streaks, and reading history |
| `BOOKMARKS_SYNC` | Sync bookmarks and reading positions across devices |

Additional user features: progress tracking, badges, recommendations, messages, reading goals, and external read history.

## Quick Start (Docker)

```bash
docker compose up -d
```

The server will be available at `http://localhost:8080`.

On first run, migrations run automatically and the database is created at `./data/database.sqlite`.

## Docker Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_KEY` | (empty) | Laravel app key — generated on first start if empty |
| `APP_ENV` | `production` | Environment (`production` or `local`) |
| `APP_DEBUG` | `false` | Enable debug mode |
| `APP_URL` | `http://localhost:8080` | Public URL of the server |
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

# Serve
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
4. Enter your server URL (e.g. `http://192.168.1.100:8080`)
5. Register or log in

## Admin User Management

Admins can manage users via the API at `POST /api/v1/admin/users`. To create the first admin user:

```bash
php artisan app:create-admin-user
```

Or register normally and then set `is_admin = 1` directly in the database.
