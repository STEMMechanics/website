# STEMMechanics Website

Public website for STEMMechanics, showcasing programs, workshops, and resources.

## Tech Stack

- Laravel (PHP)
- Vite (frontend build)
- MySQL (typical production setup)

## Requirements

- PHP 8.x
- Composer
- Node.js 18+ (22 recommended for builds in deploy script)
- A database supported by Laravel (MySQL recommended)

## Quick Start (Local)

```bash
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

## Configuration

- Copy `.env.example` to `.env` and update values.
- `APP_VERSION` is set during deploy (release tag).
- `APP_COMMIT` is set during deploy (git hash).

## Deployment

Standard Laravel deployment. This repo uses a release‑tag based flow. The server deploy script:

- Checks the latest tag
- Checks out that tag
- Runs `composer install`, migrations, and `npm run build`
- Updates `APP_VERSION` and `APP_COMMIT` in `.env`

See `CHANGES.md` for release notes.

## Contributing

See `CONTRIBUTING.md`.

## Security

See `SECURITY.md`.

## Code of Conduct

See `CODE_OF_CONDUCT.md`.

## Architecture

See `ARCHITECTURE.md` for a high‑level overview and notes on models, tables, and views.
