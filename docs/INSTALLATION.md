# Cross-platform installation

## Supported paths

| Host OS | Recommended installation |
| --- | --- |
| Linux | Docker Engine/Podman or native PHP |
| macOS | Docker Desktop or native PHP |
| Windows | Docker Desktop with WSL 2 |

Lite has no required audiobook-media mount, so its Docker installation behaves consistently across
Linux, macOS, and Windows. The image is Linux-based; Docker Desktop provides the required Linux
runtime on macOS and Windows.

## Public endpoint requirement

The mobile app only accepts HTTPS API endpoints. Set `APP_URL` and `PUBLIC_HOST` to the public DNS
name, then run the HTTPS profile:

```bash
docker compose --profile https up -d --build
```

Caddy obtains and renews the certificate and proxies to the private HTTP service. Ports 80 and 443
must be publicly reachable for certificate issuance. The HTTP port is loopback-only and must not be
configured in the mobile app.

## Native PHP installation

Install PHP 8.3 and Composer, copy `.env.example` to `.env`, set `APP_KEY`, and set `APP_URL` to
the public `https://` URL. Then initialize the application:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
```

Serve the `public` directory with IIS, Apache, nginx, Caddy, or another HTTPS-capable web server.
Run the queue worker and scheduler with the operating system's service manager (systemd, launchd,
or Windows Task Scheduler/service wrapper). Keep the PHP listener private to the host; expose only
the TLS proxy.
