# Architecture

The application uses Laravel MVC, Eloquent models, Blade templates and a Vite
frontend build.

## Source layout

| Directory | Purpose |
| --- | --- |
| `app/Http/Controllers` | Request handling |
| `app/Models` | Eloquent models and relationships |
| `app/Services` | Application services |
| `app/Jobs` | Background jobs |
| `resources/views` | Blade pages, components, emails and PDF templates |
| `resources/js` | Frontend interactions |
| `resources/css` | Application styles |
| `routes` | HTTP routes and scheduled commands |
| `database/migrations` | Database schema changes |
| `tests` | Automated checks |

## Development conventions

Keep request validation and authorization close to request handling. Use
services for shared application logic, and reuse Blade components for common
interface elements. Email and PDF templates have separate rendering constraints
from browser pages.

Use migrations for schema changes and focused tests for affected behavior.
Configuration belongs in Laravel configuration files and local environment
values, rather than credentials embedded in source code.
