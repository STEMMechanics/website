# STEMMechanics Website

Source code for the STEMMechanics website, built with Laravel, Blade and Vite.

## Requirements

- PHP 8.4+ and Composer
- Node.js 24 and npm
- A Laravel-supported database
- PHP extensions listed in `composer.json`

Media and document processing features also use ImageMagick, Ghostscript,
FFmpeg/FFprobe and Poppler. Archive and database backup features require the
corresponding command-line tools.

## Local development

Create a local environment file and configure a development database before
running migrations:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

For frontend development, run `npm run dev` in a separate terminal. Features
that dispatch background jobs require `php artisan queue:work`.

Use local configuration and development data. Keep credentials, customer data,
business documents and operational notes out of source control.

## Checks

```bash
php artisan test
node --test tests/Browser/*.test.cjs
npm run build
```

## Project documentation

- [Architecture](ARCHITECTURE.md)
- [Release notes](CHANGES.md)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of conduct](CODE_OF_CONDUCT.md)
