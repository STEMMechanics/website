# Site consistency, security and Laravel 13 readiness

Branch: `feature/site-consistency-security-laravel13`. Review date: 2026-09-05.
Includes the notification work introduced in PR #199; compare against main after that prerequisite is merged.

## Coverage and components

The accompanying `view-inventory.csv` inventories every Blade file, separating browser pages, reusable components, email, PDF and vendor views. Regenerate with `python3 scripts/audit-views.py`. Counts are source indicators, not proof that every dynamic UI state has been visually tested.

Browser page templates now use shared components for checkboxes, visible inputs, selects, textareas, buttons and tables. Hidden form payloads remain native. Existing UI components own their native markup. Email/PDF/vendor rendering retains its separate constraints.

- Select help text now renders as text by default. Dynamic help uses an explicit `info-expression`; this fixes a live Alpine syntax error on workshop creation.
- `ui.checkbox` has a bare-input mode for existing labels and table cells, retaining checked, disabled, mixed-state, Alpine bindings and accessible names.
- `ui.input-control`, `ui.select-control` and `ui.textarea-control` are the low-level controls for existing labelled wrappers, editable tables and quantity steppers. Use `ui.input`/`ui.select` for complete labelled fields. Existing layout classes and Alpine behaviour remain supported.
- `ui.button` provides shared disabled/focus behaviour. Its plain variant accommodates icon buttons, toolbars, tabs and inline actions; primary form actions should use the default variant and theme colour.
- `ui.badge` owns count/status pills, including remembered and notification devices. Dynamic status colours remain meaningful; avatars, progress bars, filter navigation and circular icons are not status badges.
- `ui.table` supports its existing header/body slots and a complete table-content slot. Both variants contain horizontal overflow; the plain variant supports calendars, editable grids and mobile backup cards without imposing data-table cell styling. Standard record tables use the default theme.
- `ui.grid` supplies a single-column, shrinkable base; 41 repeated containers use it. Standard Tailwind SM/MD/LG/XL thresholds remain 640/768/1024/1280px. Compact field groups can expand at SM/MD, major address panels at LG, dashboard cards at XL. Notification columns retain the requested LG two-column / XL one-column behaviour.
- Fixed-column candidates in the inventory include small metric tiles, thumbnail grids and proportional print/flyer previews. These need their semantic layout rather than one universal column count.
- Dashboard SVG charts no longer impose a 36rem minimum. Their parent container and cards can shrink, and card headers stack on phones. Safari's 320px responsive viewport shows the charts contained within their cards. The same check found and corrected mobile navigation overflow caused by a fixed-size logo and desktop action spacing.

## Security review

Implemented:

1. Login throttling keys off the actual `login` field, with email fallback. Extra random `email` fields can no longer change the login key; a separate IP ceiling covers rotating identifiers. Magic-link requests also have an IP ceiling. Registration now uses the public-form throttle.
2. Login token consumption checks the atomic delete result before authenticating, preventing two concurrent readers from both redeeming one token.
3. Post-login redirects accept relative paths or the configured application origin, rejecting external/protocol-relative URLs, credentials, whitespace and backslashes.
4. Audit and form-guard logs and error email context omit URL query strings. SMS callback logging omits full headers and message payloads; SMS content remains in its intended application records.
5. JSON-LD is encoded safely for a script element; flash alerts use JavaScript-safe encoding.
6. Account/admin/auth and token-bearing responses have `X-Robots-Tag: noindex, nofollow`; token/signature URLs also use `Referrer-Policy: no-referrer`.
7. Private/no-store response caching remains intact and now preserves existing `Vary` fields such as Origin.
8. Native confirmation fallback was removed from push settings; removal consistently uses the themed popup.

Existing controls reviewed include administrator route middleware, account/media ownership checks, protected-media download checks, Square webhook signature verification, bound SQL values, hidden user credentials, session regeneration at login and invalidation at logout. Existing authorization, login, account, media and webhook feature tests exercise these areas. Composer and npm dependency scans reported no known vulnerabilities at review time.

### Unresolved deployment and integration findings — controls implemented

The application controls and deployment assets for these findings are implemented in this branch. The corresponding external rollout is documented in [deployment/README.md](../deployment/README.md); `.env` and provider/network settings have not been changed.

- **SMSFlow:** dedicated 32+ character inbound secret, constant-time bearer/query credential comparison, fail-closed rejection before payload handling and a 64KiB payload ceiling. Existing unauthenticated callbacks will receive 401 until the provider URL/header and `SMSFLOW_WEBHOOK_SECRET` match. Outbound API keys are not reused or appended to arbitrary destinations.
- **Proxy/host trust:** only configured literal proxy addresses/CIDRs are trusted; wildcard aliases and host-based cloud autodetection are excluded. Forwarded host is ignored. Trusted hosts are exact configured names. Canonical GET/HEAD redirects target `APP_URL`; signed/token links and webhook callbacks retain their original URL.
- **Operational privacy:** error emails contain a correlation reference, class, method and route only. Explicit recipients are required. Production log processors remove exception diagnostics and sensitive structured context; rotation defaults to daily/14 days. Deployment permissions use 0640 files/0750 private directories. MFA QR secrets now travel through session state instead of URL queries. Historical logs and arbitrary legacy free-text log messages are not retrospectively sanitised.
- **Authentication policy:** express initiation opens TOTP first, otherwise password, otherwise email verification. Email-link requests retain generic responses. Administrators must enrol and verify an authenticator before privileged access; accounts with an enrolled authenticator retain direct TOTP/backup-code sign-in, including administrators; verification expires after 12 hours, a new login or secret change. Backup codes are atomically consumed, TOTP challenges reject replay, and required administrator MFA cannot be disabled through the account endpoint. Enrolment and recovery paths remain available. These policies default on outside automated tests and have explicit enabled-policy regression coverage.
- **Deployment verification:** `security:deployment-check` validates HTTPS/debug/cookie/shared-cache/queue/secret/MFA/log/indexing configuration without printing secrets. The deployment script runs it against rebuilt configuration and leaves the application in maintenance mode if it fails. Nginx and analytics-worker templates plus firewall/TLS/backup restoration/provider/recovery checks are included.

The current development configuration fails the production preflight for debug mode, secure cookies, missing callback secret and missing explicit alert recipients. No production firewall, TLS renewal, provider callback, backup restore or credential rotation has been verified by changing this repository. Those are rollout requirements, not completed infrastructure operations.

## Performance, SEO, mobile and caching

Implemented performance changes: editor JavaScript is now loaded by editor components only. Main production JS decreased from approximately 608KB to 126KB (37KB gzip); editor-only shared code is in separate chunks. Fabric was already dynamically imported. Shop availability is request-scoped, allowing navbar/footer/layout checks to reuse its query results without long-lived stale public visibility.

### Further improvements worth measuring — implementation and measurements

- **Caching:** Nginx template gives hashed Vite assets a one-year immutable lifetime, keeps the service worker revalidating and denies sensitive files. Laravel responses remain private/no-store, including MFA redirects. Ingress/CDN adoption still requires the deployment operator.
- **Dashboard/report/export measurement:** `performance:dashboard` measures live query counts and timings without SQL/bindings. With 39,225 analytics rows, three overview builds made 42 queries each: 335.56/271.76/291.97ms database time and 344.91/276.13/296.89ms total. `PROFILE_REQUESTS` adds route-only timing/query/memory logs, including streamed report/export completion, for representative workload comparisons.
- **Scheduled snapshots:** dashboard aggregates warm every five minutes, use JSON cache payloads, expire, restore dates/row types, and use a lock on cold cache misses. Cache failures fall back to live reporting. The UI identifies when figures were generated. Financial exports remain live; snapshots are dashboard summaries. Setting the snapshot TTL to zero disables serving cached figures.
- **Analytics ingestion:** page-view events are queued on `analytics`, with encrypted payloads, three bounded attempts, backoff and a UUID unique constraint to make retries idempotent. Queue failures do not break public pages; existing bot filtering remains. A dedicated worker template and seven-day failed-job pruning are included.
- **Mobile measurements:** pinned Lighthouse 13.0.3 mobile/simulated-network runs cover home, store and a published workshop. Baseline performance scores were 57/59/56; LCP 11.7/6.8/10.9s; CLS 0.042/0/0; TBT 80/130/110ms. Responsive static image variants, smaller hero assets, explicit image dimensions/priority, responsive product/hero sources and non-blocking Google Font CSS were added. The follow-up home run scored 64, with 9.4s LCP and 20ms TBT; CLS was 0.081. These single-run lab results still show slow LCP, especially render-blocking legacy scripts/CDNs; they are not field INP or production Core Web Vitals. See [mobile-measurements.json](mobile-measurements.json) and the reproducible `scripts/measure-mobile.sh` runner. Staging noindex intentionally lowers SEO scores.
- **Sitemap:** corrected the escaped XML declaration; static pages omit invented dates, managed pages use actual update times, published/indexable custom pages and active public-store products are included, duplicates are excluded, and catalogues over 1,000 URLs use a sitemap index with bounded database pagination.
- **Indexing/canonical origin:** non-production responses default to noindex; private routes retain their own noindex rules. Production canonical redirects use the configured origin and exact trusted host aliases. The ingress template also documents staging-wide noindex. These headers do not replace authorization.
- **CSP:** strict script restrictions run report-only alongside the existing enforced protections. The bounded/throttled report endpoint retains only directive and violation category, deduplicated for five minutes; it omits URLs, referrers and script samples. [csp-inventory.txt](csp-inventory.txt) inventories inline handlers/scripts and external origins for staged nonce/module migration. Enforcement is intentionally not promoted until those violations are resolved.

## Laravel 13 preparation

The application remains on Laravel 12. Dependencies and `.env` are unchanged. `bash scripts/check-laravel13.sh` resolves Laravel 13 in a disposable directory with scripts disabled and the declared PHP 8.4.17 platform. The successful dry run selected Laravel 13.30.1 with 31 updates and one new dependency. This is dependency feasibility, not a Laravel 13 runtime test.

Configuration now explicitly preserves PHP session serialization to avoid unexpectedly invalidating sessions, and declares scalar/array-only cache deserialization. Existing cache/Redis/session names are explicitly configured. Before migration, review session objects, change CSRF middleware references/exclusions to `PreventRequestForgery`, and test same-origin/cross-origin requests and every webhook exemption. The relevant changes are documented in the [official upgrade guide](https://laravel.com/framework/docs/13.x/upgrade).

Migration sequence:

1. Merge the notification prerequisite and this reviewed preparation branch; take restorable database/file backups.
2. Create a dedicated upgrade branch, change `laravel/framework` to `^13.0`, resolve and review the complete lockfile diff. Tinker is already 3.x. Retain only PHPUnit/tool versions that resolve on CI's actual PHP version.
3. Update request-forgery middleware references in bootstrap and tests; retain only necessary exemptions. Test tokens/origin headers without disabling middleware globally.
4. Run full PHPUnit, JS checks, PHPStan, view compilation and production build under the deployment PHP/Node versions. Exercise email login, TOTP, remembered devices, admin permissions, uploads, signed downloads, checkout, payments/refunds, webhooks, scheduled jobs, queue workers and PDF generation in staging.
5. Rebuild config/routes/views, restart queue workers and PHP processes, then verify login continuity and cache compatibility. Clear old cache namespaces deliberately if needed; keep rollback assets and database compatibility until acceptance.

Local checks use PHP 8.5 and Node 26; deployment declarations target PHP 8.4 and Node 24. The Composer dry run honours PHP 8.4, but actual CI execution on those declared versions remains required.

## Validation record

- Composer audit and npm audit: zero known advisories reported. Unauthenticated requests to `/.env`, `/.git/config`, `/composer.json` and `/storage/logs/laravel.log` on the test host all returned 404; production still needs its own ingress verification.
- PHPStan (project configuration and baseline): no errors.
- Production Vite build and JavaScript tests: successful; all 24 JS checks pass.
- Deployment-stage full regression suite: 804 tests, 5,390 assertions, all passed. Subsequent login preference changes and their targeted checks are recorded below.
- All Blade views compile and compiled PHP is syntax checked.
- Safari: dashboard at 320px has `documentElement.scrollWidth === innerWidth === 320`; workshop creation renders two editable ProseMirror instances with the page-specific editor asset loaded. This is targeted browser validation, not a claim of exhaustive visual coverage across all pages, devices and content states.
- `.env` and user-written notification empty-state copy remain unchanged. Address/notification breakpoints preserve the user's settings.

## Login and notification follow-up

Express login opens the authenticator when enrolled, otherwise password when configured, otherwise email verification. Verified accounts can sign in with their existing TOTP or unused backup code, including administrator accounts; that verification satisfies the administrator gate. Accounts without an enrolled authenticator cannot use the code path. General notifications now use themed, dismissible cards below the navbar, with placement tracking navigation height. Push-device and remembered-device successes appear inline beside their controls. Error banners persist until dismissed; success banners pause their timer on hover or focus. Shared confirmation and acknowledgement dialogs remain centred. Existing user copy and `.env` remain unchanged.

Follow-up validation: 51 authentication/account/security PHP tests and 26 JavaScript tests pass; PHPStan and production build pass.

## Deployment configuration visibility

Server Info includes shared deployment configuration checks with Correct, Needs attention and Review needed states, configuration key names and remediation instructions. The CLI uses the same checks. Results include callback credentials/URL validity, proxy and host syntax, queue/cache drivers, recipients/log retention, indexing, snapshots, profiling, CSP and analytics migration readiness. Runtime workers, ingress/TLS, private-file permissions and backup restoration are explicitly manual checks. No credential values are returned. The administrator verification dialog now right-aligns its Verify button.

Configuration visibility validation: 32 PHP tests (232 assertions), PHPStan and production build pass. Administrator access, secret redaction, callback validation and shared CLI results are covered.

Express login restored by preference: after entering the account email, open TOTP when enrolled, otherwise password when configured, otherwise send email verification. “Sign in another way” keeps password, email and backup-code alternatives available where applicable. The separate method chooser was removed. Account-specific initiation is intentional; GENERIC_LOGIN now controls generic email responses.

Express-login validation: 57 authentication/account/configuration tests (271 assertions), PHPStan and production build pass. Coverage includes TOTP preference with a password present, password/email fallback and switching back to the authenticator.

### Media table interaction alignment

Workshop files and the media library share a persistent selection toolbar above their tables, with an empty-state hint, selected-count pill, clear selection and disabled bulk actions until items are selected. Shared row-action buttons use right alignment, 44px touch targets, accessible names and soft semantic colours; the media card view uses the same controls. Bulk editor save controls are right-aligned and consistently labelled. Existing media selection across pages and workshop upload behaviour are preserved.

### Wider table consistency follow-up

Reviewed and aligned 44 table views across admin and customer account pages. Action headings and controls align right, with shared `ui.row-actions` groups and `ui.row-action` buttons, semantic soft colours, accessible labels, regular/solid icon support and preserved form submissions, links and Alpine attributes. Workshop lists, workshop photos and expense export now use the persistent selection toolbar above the list, including clear-selection and disabled empty-state actions. Bulk workshop editors use consistent save wording. Photo bulk edits retain “Apply to selected” because that operation stages metadata before saving.

Validation: 368 compiled Blade templates passed PHP syntax checks; the build and 29 browser-script tests passed. The full 824-test PHP run had one obsolete centre-alignment assertion; it was updated for right alignment and the subsequent 80-test targeted run passed.

### Table alignment and wrapping correction

The shared action groups and all action-column headings are now centred. Action groups stay on one line, with horizontal table scrolling retained on narrow screens. Shared `ui.nonbreaking` spans keep file sizes intact. `ui.date-time` protects the date and time separately and permits wrapping only between them, preserving the existing display format. Date-only and time-only values remain intact. Applied to 54 browser views; form input values and printed/email templates are unchanged.

Size and date/time column headings and values are centred consistently, including sortable backup columns. Existing non-breaking size values and date/time wrap boundaries are preserved.

Status, type and price columns now centre both headings and values across browser tables. Equivalent monetary columns (amount, total, balance, cost and tax) follow the same rule, including headings with GST qualifiers. Product names, descriptions and other identifying text keep their existing alignment.

### View navigation

Product filters, media filters/layouts and the public workshop card/calendar switch use shared `ui.view-tabs` navigation: labelled links, an underlined current view, `aria-current="page"`, keyboard focus indicators and horizontal overflow on narrow screens. These are full-page links rather than ARIA tab widgets. Existing filter queries and inactive-view routes are retained; create, export and processing controls remain actions. Existing mast navigation already uses links.

### Dynamic listings and reports

48 public, account and admin listing/report views now opt into `ui.dynamic-list`, including media, workshop files/photos/attendance, the dashboard, BAS and workshop reports. Shared GET search (350 ms debounce, including site search `q`), view links, calendar month links and pagination fetch fresh server HTML and replace only the listing region. Requests cancel when superseded; network errors, a 20-second timeout, browser history, input focus/caret and IME composition are handled. Filters reset standard and named paginator parameters. Links and GET forms retain normal navigation without JavaScript; incompatible fragments, session redirects and error responses fall back to normal navigation. POST actions and navigation to different pages retain their existing flows.

This uses the existing Blade controllers and Alpine components; a Livewire rewrite is not required for listing updates. Responses remain full HTML documents with client-side region extraction, so server query/render costs are unchanged. Page scripts load once, with `SM.onDynamicList` reconnecting DOM-bound controls after replacement and cancelling old global listeners. Bulk media/workshop/export selections retain their session storage behaviour. Upload forms, backup operations and attendance drop-in drafts stay outside refreshed regions; explicitly persistent newsletter composition and media regeneration controls retain their DOM nodes.

Verification: 43 JavaScript tests cover request races, URL/filter preservation, history, focus, persistent drafts, result-area loader placement and lifecycle cleanup. The full PHP run covered 827 tests and identified attendance template boundaries and an old alignment assertion, subsequently fixed; targeted follow-up runs verified these and the additional dashboard/report views (updating the dashboard assertion to `requestSubmit`). Blade compilation and production build pass. Authenticated Safari verification confirmed live media search, table/photo switching, preserved bulk selection and browser Back without replacing the upload area. This is representative verification, not visual coverage of every listing state.

### Loading feedback and notification dismissal

Dynamic listings position the shared circle loader over the visible result table/cards, without a blank loading row above the toolbar, and use a fixed 4px theme-coloured indeterminate bar at the top of the viewport. Loading/success text remains available to screen readers, while visible text is replaced by the indicators. Indicators stop animating when idle.

The notification popup previously had a permanent entrance-animation declaration that overrode its closing animation. Separate finite enter/leave classes now let SweetAlert finish both close-button and timer dismissal. Success/info retain the seven-second timer with hover/focus pause; warnings/errors remain dismissible until read. Decorative circles animate on separate pointer-transparent pseudo-elements, with randomised 22–40 second durations and small drift distances. Reduced-motion preferences disable decorative animation. Safari verification was interrupted by concurrent user interaction; automated notification tests, template compilation and build were run.

The shared view tabs now sit on a bordered slate track with a white active tab, theme-coloured underline and subtle shadow for clearer contrast against page backgrounds.

### Media collection redesign (6 September 2026)

Media is the first page using the desktop/mobile mockup pattern. New reusable preset views, sortable headings, filter chips, native dialog/action sheets, page-upload and pagination components sit alongside the opt-in `ui.table` listing variant. All/Images/Unused presets include counts. Desktop headers sort; a compact sort sheet exposes the same fields on small screens. General, size, date and workshop/location filters render as sidebar groups on desktop and accordions on mobile. Active chips can be removed individually. Mobile checkboxes appear in selection mode, with a fixed bulk action bar; desktop checkboxes remain visible. Matching selection is confirmed and capped at the existing 500-file bulk editor limit.

The old upload panel is replaced by Upload plus page-wide file drag/drop feedback. Existing chunked upload handling is retained, with concurrent drops blocked and progress shown separately. Uploads, quick metadata edits and deletion refresh results through AJAX. Full editing, duplicate tools and regeneration remain available. Quick editing updates only title, visibility, caption and tags; it cannot change ownership, passwords or storage, and protected-link revocation follows visibility changes. Sorting is allowlisted and filter/page-size values are bounded. Unused reference discovery is shared within a request for filtering and counts.

Validation: the Media/public-media PHP group passed 50 tests (329 assertions); 47 JavaScript tests passed, including file-drop lifecycle and native-dialog error feedback. Desktop Safari verified the layout and AJAX size filtering/chip updates. Mobile visual inspection was interrupted by concurrent user browser activity; the responsive layout and sheets are implemented but still need a phone visual pass. No real library files were uploaded, edited or deleted during browser verification.


### Media mast and advanced filters — 6 September 2026

- Expanded the shared mast with optional breadcrumb and action slots; Media now places its heading, description, upload and tools in the blue mast.
- Presets apply ordinary editable filters and clear previous filtering. Legacy preset URLs are normalized to removable chips. Removed the unused-media explanatory paragraph.
- Added combined usage, title/filename wildcard, MIME alternatives and whole-tag include/exclude filters. Wildcards use bound SQL with literal SQL metacharacters escaped; all required tags must match and excluded tags must be absent. Bulk selection shares these filters.
- Centered filter dialogs on all viewport sizes. Filter dialog search waits for Apply; toolbar search remains live. Enlarged list spinners and removed their background, border and shadow.
- Validation: Media feature suite passed (31 tests), additional advanced filtering checks passed (9-test list suite), 47 JavaScript tests passed, production build and Blade compilation passed. Safari desktop inspection confirmed the mast, normalized chips and centered filter dialog.


### Media bulk editor popup — 6 September 2026

- Replaced the separate bulk editor view with a lazily loaded dialog partial. Desktop uses a centered popup; mobile uses the shared sliding bottom panel. Old editor URLs redirect to Media.
- Preserved shared/mixed metadata, storage moves, ownership and workshop links, and adopted the shared tag input. The popup submits changed fields through AJAX and retains the listing's current URL after saving.
- Popup requests validate their own explicit selection (1–500 existing distinct media names); JSON saves cannot fall back to another tab's session selection. Errors stay in the popup and selections are cleared only after successful saves.
- Validation: 18 focused PHP tests (90 assertions), 48 JavaScript tests, production build and Blade compilation passed. Safari confirmed opening, tag chips and cancellation without saving actual media.

### Remembered sign-in across page visits — 6 September 2026

- Moved remembered-device restoration into the web middleware pipeline after session startup and before authentication/authorization. Public pages and the PWA's admin dashboard now restore valid remembered cookies, without requiring a trip through the login page.
- Explicit login URLs retain their existing token/intended-destination handling. JSON requests and state-changing requests do not silently establish a remembered session. Restored sessions regenerate their ID, and expired/revoked tokens remain invalid.
- Kept the separate 12-hour administrator MFA confirmation requirement. A fresh authenticator prompt after that window is distinct from losing remembered sign-in; the reported phone symptom has not yet been distinguished between those cases.
- Validation: 22 remembered-auth tests (89 assertions), plus 16 deployment/account-security tests (86 assertions), passed. Includes simulated overnight expiry, public-page restoration, PWA admin landing, expired cookies, JSON rejection and enforcement of fresh MFA after restoration. No changes to `.env` or cookie lifetimes.

## Collection design rollout

The Media collection controls now underpin administrative and account lists, the public catalog/workshop lists, and independently scoped analytics, BAS and search result lists. Explicit field registries in `config/listings.php` and `config/report-listings.php` keep filter and sort columns allowlisted. Queries retain their existing access scopes, apply filters before pagination, and preserve nested query values across navigation. Workshop history exports use the same query filters as the report; BAS table filters deliberately leave whole-month totals and exports intact and explain this beside the controls.

The layout pass moves list creation actions into the existing mast, adds breadcrumbs, uses the Media white/bordered table surface and action menus, and removes the old toolbar split around collection controls. Presets expose their rules as editable chips and display counts, including ghost accounts, archived/actionable products, cancelled tickets/orders, reminders and unallocated payments. Product attention counts reuse the inventory summary calculation in bounded chunks; this is a useful query-cost measurement point for large catalogs.

MySQL-specific Square webhook group-key expressions remain MySQL-specific. Aggregate filtering is covered separately using a derived-query regression test; SQLite route smoke coverage excludes those Square pages.

Validation: the full PHP regression run completed with 851 of 852 tests passing; the remaining failure asserted the removed dashboard select's `onchange` attribute. That assertion now checks the preset navigation, and all three dashboard tests pass. The final workshop layout check passes 14 tests (112 assertions), the Users mast/count/surface check passes, all 48 browser JavaScript tests pass, the production asset build succeeds, and Blade compilation and whitespace checks succeed. Safari visual verification confirmed the Users mast, full-width control row, table surface, action menus and live preset counts.
