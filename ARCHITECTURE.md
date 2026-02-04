# Architecture

High‑level overview of the site’s structure and data model.

## Overview

- Laravel MVC application.
- Blade templates for views.
- Eloquent models for data access.
- Media storage uses the `media` disk with generated variants.

## App Structure

- `app/Http/Controllers`: request handling and view composition.
- `app/Models`: Eloquent models for core domain entities.
- `resources/views`: Blade templates and components.
- `routes/web.php`: public, auth, and admin routes.
- `database/migrations`: schema definitions.

## Core Routes

- `/`: home (shows upcoming workshops).
- `/workshops`: list of upcoming workshops.
- `/workshops/past`: list of past workshops.
- `/workshops/{workshop}`: workshop details.
- `/media`: media index.
- `/media/{media}`: media detail.
- `/media/download/{media}`: download.
- `/admin/*`: admin CRUD for media, locations, users, and workshops.

## Data Model

Key tables and relationships from the migrations/models:

- `users`: UUID primary key, profile details, shipping/billing addresses, 2FA fields.
- `tokens`: single table for short‑lived tokens (email update, login, etc.).
- `user_backup_codes`: hashed backup codes for 2FA.
- `email_subscriptions`: email list subscriptions and confirmation timestamps.
- `sent_emails`: simple record of sent mailables.
- `locations`: venue details for workshops.
- `workshops`: core program listings with schedule, status, registration data.
- `tickets`: user registrations for workshops.
- `posts`: optional content posts (routes currently commented out).
- `media`: uploaded files with variants and ownership.
- `mediables`: polymorphic join table for attaching media to models.

Relationships (as implemented in models):

- `Workshop` belongs to `User` (author), `Location`, and `Media` (`hero_media_name`).
- `Post` belongs to `User` (author) and `Media` (`hero_media_name`).
- `Media` belongs to `User` and morphs to `mediable`.
- `User` has many `Token`, `Ticket`, and `UserBackupCode`.

## Views

Key views and their intent:

- `resources/views/home.blade.php`: homepage, shows upcoming workshops.
- `resources/views/components/footer.blade.php`: global footer and version display.
- `resources/views/workshops/*`: workshop listing and detail pages.
- `resources/views/media/*`: media listing and detail pages.

## Admin Area

Admin routes are protected by the `admin` middleware:

- Media CRUD.
- Locations CRUD.
- Users CRUD.
- Workshops CRUD (including duplicate).

## Deployment Notes

- Release‑tag based deployment.
- `APP_VERSION` and `APP_COMMIT` are set during deploy.
