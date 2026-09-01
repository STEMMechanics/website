# STEMMechanics Website

Public website for STEMMechanics, showcasing programs, workshops, and resources.

## Tech Stack

- Laravel (PHP)
- Vite (frontend build)
- MySQL (typical production setup)

## Requirements

- PHP 8.4+
- Composer
- Node.js 18+ (22 recommended for builds in deploy script)
- A database supported by Laravel (MySQL recommended)

### System dependencies

Composer installs the PHP libraries, but it does not install PHP extensions or
the operating-system tools used for media processing, PDF search, archives, and
backups.

| Dependency | Used for |
| --- | --- |
| PHP extensions: `fileinfo`, `imagick`, `mbstring`, `zip` | Required by `composer.json` |
| ImageMagick and Ghostscript | Image variants, PDF previews, and combining PDF/image attachments |
| FFmpeg and FFprobe | Video thumbnails and metadata |
| Poppler (`pdftotext`) | Searchable text extraction from expense PDFs |
| `zip` and `unzip` | File and media backup archives |
| MySQL client and `gzip` | Database backup and restore |

On Debian or Ubuntu, install the common runtime dependencies with:

```bash
sudo apt-get update
sudo apt-get install -y \
    php-cli php-fpm php-mysql php-curl php-mbstring php-xml php-zip php-imagick php-intl php-bcmath \
    imagemagick ghostscript ffmpeg poppler-utils \
    zip unzip default-mysql-client gzip git curl
```

PHP package names may include the installed PHP version on distributions using
an external PHP repository, for example `php8.4-imagick` instead of
`php-imagick`.

On macOS with Homebrew:

```bash
brew install php composer node imagemagick ghostscript ffmpeg poppler zip mysql-client
pecl install imagick
```

Confirm the important PHP extensions and external tools are available:

```bash
php -m | grep -E 'fileinfo|imagick|mbstring|zip'
magick -version || convert -version
gs --version
ffmpeg -version
ffprobe -version
pdftotext -v
zip -v
mysqldump --version
gzip --version
```

Production also requires a process manager such as Supervisor or systemd to
keep `php artisan queue:work` running. Search-document indexing and other
queued jobs depend on those workers.

## Quick Start (Local)

```bash
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

Run the queue worker in a separate terminal:

```bash
php artisan queue:work
```

After importing an existing expense database and its stored attachments into a
development environment, dispatch the one-time PDF text indexing backlog:

```bash
php artisan search:index-documents
```

Starting a queue worker processes jobs that have already been dispatched; it
does not create document-indexing jobs. Production deployment runs this
dispatch command automatically.

## Configuration

- Copy `.env.example` to `.env` and update values.
- `APP_VERSION` is set during deploy (release tag).
- `APP_COMMIT` is set during deploy (git hash).

## Keys And Secrets

The site uses a mix of environment variables and database-stored options. The most important values are:

| Key or secret | Where it lives | Notes |
| --- | --- | --- |
| `APP_KEY` | `.env` | Laravel encryption key. Rotating it invalidates encrypted cookies and sessions. |
| `DB_PASSWORD` | `.env` | Database user password used by Laravel. |
| `REDIS_PASSWORD` | `.env` | Redis password, if Redis auth is enabled. |
| `MAIL_PASSWORD` | `.env` | SMTP password for outbound mail. |
| `ALTCHA_HMAC_KEY` | `.env` | Used to sign ALTCHA challenges. |
| `LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET` | `.env` | Used for LiveKit access tokens and webhook validation. |
| `SQUARE_ACCESS_TOKEN` / `SQUARE_WEBHOOK_SIGNATURE_KEY` | `.env` | Square API credentials and webhook validation secret. |
| `FLARE_KEY` | `.env` | Flare error reporting API key. |
| `POSTMARK_TOKEN` / `MAILGUN_SECRET` | `.env` | Alternate mail provider credentials, if configured. |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | `.env` | Used by cache, queue, and SES/DynamoDB integrations if enabled. |
| `PUSHER_APP_SECRET` | `.env` | Pusher auth secret if broadcasting is enabled. |
| `SLACK_BOT_USER_OAUTH_TOKEN` | `.env` | Slack notifications token if configured. |
| `minecraft.webhook-secret` | Database site option | Managed in the admin Site Options screen. Used to sign STEMCraft webhook traffic. |

If you suspect a leak, rotate the affected credential at the provider, update the matching value here, and then clear any cached config if needed.

## Deployment

Standard Laravel deployment. This repo uses a release‑tag based flow. The server deploy script:

- Checks the latest tag
- Checks out that tag
- Runs `composer install`, migrations, `npm run build`, and queues the expense PDF text backfill for the normal queue workers
- Updates `APP_VERSION` and `APP_COMMIT` in `.env`
- Uses `scripts/deploy.sh` by default, with `DEPLOY_SCRIPT_PATH` available if you need to override the location.

Production permissions expected by the app and deploy script:

- The deploy user must be able to write to the repository working tree, `.git`, `.env`, `public/build`, `node_modules`, `vendor`, `bootstrap/cache`, and `storage`.
- The web user must be able to read the checked-out app and write to `storage` and `bootstrap/cache`.
- Deploy-time Git, npm, and Composer state is stored under `storage/app` (`deploy-gitconfig`, `npm-cache`, `composer-home`, and `composer-cache`) so the deploy does not depend on a writable `/var/www` home directory.
- Shared writable directories should normally be owned by the deploy/web user group with directories `775` and files `664`. In a typical Debian/Ubuntu PHP-FPM setup, keep `storage` and `bootstrap/cache` accessible to `www-data`.
- The deploy script runs best-effort permission repair on `public`, `storage`, and `bootstrap/cache`. When run as root, it also changes mutable deploy paths such as `.git`, `.env`, `public/build`, `node_modules`, `vendor`, `storage`, and `bootstrap/cache` to `www-data:www-data` by default. Override with `DEPLOY_WEB_USER` and `DEPLOY_WEB_GROUP` if the container uses a different deploy/web user.
- Local file backups live under `storage/app/backups/files`; backup run directories must remain readable by the web user so the admin backups page can list and inspect them.

See `CHANGES.md` for release notes.

## Contributing

See `CONTRIBUTING.md`.

## Security

See `SECURITY.md`.

## Code of Conduct

See `CODE_OF_CONDUCT.md`.

## Architecture

See `ARCHITECTURE.md` for a high‑level overview and notes on models, tables, and views.
