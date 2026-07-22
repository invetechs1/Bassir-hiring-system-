# Laravel Shared Hosting Installation

## Requirements

- PHP 8.2 or newer.
- Composer.
- MySQL 8 or MariaDB 10.6+.
- PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `zip`.

## Deployment Mode A: Preferred Document Root

1. Create a MySQL database and user.
2. Upload this folder outside `public_html`, for example `/home/account/bassir`.
3. Set the domain/subdomain document root to `/home/account/bassir/public`.
4. Set:

```env
APP_URL=https://your-domain.com
```

5. Run:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan bassir:create-owner --username=yahya --email=owner@example.com --name="Bassir Owner" --company="Bassir Technology"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php scripts/preflight.php
```

For final production cutover automation, run:

```bash
RUN_SEED=true ./scripts/target-cutover.sh
```

The seeder creates default roles, the owner account, demo candidates/jobs, and the initial engineering specialization list.
The seeded owner account is marked with mandatory password change at first login.
Use `bassir:create-owner` to set a production owner password privately; do not publish production credentials in tickets or frontend text.

## Deployment Mode B: Shared Hosting Subfolder Fallback

Use this mode only when the host cannot point the domain/subdomain document root to `public/`.

Example installed path:

```bash
/home/account/public_html/rec
```

Public URL:

```text
https://your-domain.com/rec/public
```

Required `.env`:

```env
APP_URL=https://your-domain.com/rec/public
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
TRUSTED_HOSTS=your-domain.com,www.your-domain.com
```

Required permissions:

```bash
chmod 755 /home/account/public_html/rec
chmod 755 /home/account/public_html/rec/public
chmod 644 /home/account/public_html/rec/public/.htaccess
chmod 600 /home/account/public_html/rec/.env
```

This mode works, but it exposes the Laravel project folder under `public_html`. Prefer Mode A before go-live whenever the hosting control panel allows it.

## If Public Files Must Be Moved

Move only the contents of `public/` into `public_html/`, then edit `public_html/index.php`:

```php
require __DIR__.'/../bassir/vendor/autoload.php';
$app = require_once __DIR__.'/../bassir/bootstrap/app.php';
```

Adjust `../bassir/` to your actual private application path.

## Security Checklist

- Keep `.env` outside `public_html`.
- Keep `storage/app/private` outside public access.
- Set `APP_DEBUG=false`.
- Set `SESSION_SECURE_COOKIE=true` for HTTPS production domains.
- Set `APP_FORCE_HTTPS=true` for production.
- Configure `TRUSTED_PROXIES` when SSL terminates at CDN/load balancer (example: `*`).
- If your proxy terminates SSL but PHP still sees HTTP, set `APP_TRUST_PROXY_HTTPS_HEADERS=true` after confirming the site is behind the proxy.
- Set `TRUSTED_HOSTS` to your real domain list.
- Set logging to daily rotation (`LOG_STACK_CHANNEL=daily`, `LOG_DAILY_DAYS=14`).
- Use HTTPS.
- Change the seeded owner password immediately.
- Configure backups for MySQL and private CV storage.
- Store API keys using `/integrations` (encrypted) or private `.env`.
- Optional OCR integration requires `OCR_SPACE_API_KEY`.
- Optional malware scan hook can be configured with `CV_MALWARE_SCAN_COMMAND`, for example a ClamAV command when available on the host.
- Do not enable unapproved scraping.
