# Installation

AbLibrarian Lite synchronizes listening events, positions, statistics, and achievements. It does
not require an audiobook-media mount or an external document database.

## Docker

```bash
cp .env.example .env
docker compose up -d
```

The default HTTP listener is local-only. For a mobile connection, configure a public HTTPS URL in
`APP_URL` and place a TLS reverse proxy in front of the application. The bundled Caddy profile can
obtain and renew certificates:

```bash
docker compose --profile https up -d --build
```

Set `PUBLIC_HOST` to the public DNS name. Ports 80 and 443 must be reachable for certificate
issuance; do not expose the container's private HTTP listener directly to devices.

## Native PHP

Install PHP 8.3, Composer, and SQLite or MySQL. Then:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan config:cache
```

Serve `public/` behind HTTPS. Configure the process manager to run the scheduler only if you use
scheduled account-deletion cleanup. No media-processing worker is required for Lite's sync,
statistics, or achievement functions.
