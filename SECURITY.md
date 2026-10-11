# Security Policy

## Reporting a Vulnerability

If you discover a security issue, please report it privately.

- Email: hello@stemmechanics.com.au
- Please include steps to reproduce and impact assessment if possible.

We will acknowledge receipt within 72 hours and provide a timeline for remediation.

## Supported Versions

Only the latest release tag is supported.

## UI Implementation

- Render plain text with escaped Blade output (`{{ }}`) or DOM `textContent`. Labels, errors, notices and tooltips must not interpret user text as HTML.
- Serialize PHP values used by JavaScript with `@js()` or `Illuminate\Support\Js`. Do not interpolate content into JavaScript strings or template literals.
- Raw HTML output (`{!! !!}` or `x-html`) requires an explicit trusted or sanitized content source. Preserve any existing sanitization when refactoring rich-text views.
- Use shared links and buttons for navigation. `App\Support\UiLink` validates static URLs; `SM.safeHref` validates bound URLs. Navigation allows relative URLs, HTTP(S), email, telephone and SMS links, and rejects script protocols. New-tab links must include `rel="noopener"`.
- Use Alpine `x-on:*` or module event listeners instead of native inline `onclick`, `onchange`, etc. Keep the existing CSP nonce and Rocket Loader opt-out on trusted scripts; do not weaken the CSP to accommodate a UI control.
- State changes require the existing server authorization, validation and CSRF protection. Hidden panels, disabled controls and client-side validation do not enforce permissions.
- Review dependency advisories with `composer audit --locked` and `npm audit` when updating dependencies. Audit results do not establish that the application itself is secure.

Reference: [OWASP Cross Site Scripting Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html).
