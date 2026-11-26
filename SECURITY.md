# Security Policy

## Reporting a Vulnerability

If you discover a security issue, please report it privately.

- Email: hello@stemmechanics.com.au
- Please include steps to reproduce and impact assessment if possible.

We will acknowledge receipt within 72 hours and provide a timeline for remediation.

## Supported Versions

Only the latest release tag is supported.

## Dependency monitoring and notifications

The `dependency-audit` workflow reads `composer.lock` and `package-lock.json`
on every PR, on dependency changes merged to `main`, and daily at 08:17
Australia/Brisbane (22:17 UTC). GitHub may delay scheduled runs. All three
audits run independently without installing or executing project dependencies.
Each run publishes a summary and retains the raw reports for 30 days.

High or critical findings in production **or development** dependencies create
one GitHub issue assigned to `nomadjimbob`. Advisories without a known severity
also require review. Other Composer findings, abandoned packages, validation
failures and incomplete scans create an assigned review issue. The issue is
updated on subsequent runs; changed findings generate a notification comment.
Unchanged daily findings do not generate repeated comments. A complete scan
without outstanding actionable findings closes the issue; a scan failure never
resolves it. Lower-severity npm findings remain in the report.

The PR gate still blocks on all Composer advisories/abandoned packages and on
high or critical production npm findings. Development npm findings are reported
and can raise an urgent issue without blocking a production PR. Only trusted
push, scheduled and manual runs can write issues; PR jobs have read-only access.

Dependabot PRs for Composer, npm and GitHub Actions are assigned to `nomadjimbob`.
Assignment does not override personal GitHub email preferences. To receive mail:

1. In this repository's **Watch → Custom** settings, select **Pull requests**,
   **Issues** and **Security alerts** (or watch all activity).
2. In [GitHub notification settings](https://github.com/settings/notifications),
   enable email for participating/watching activity, verify the delivery address
   and organization custom routing, and enable Dependabot vulnerability emails.
   A daily or weekly Dependabot digest provides an additional review reminder.
3. Check spam filters if an event appears in the GitHub inbox but not in email.

To test assigned-issue delivery, open **Actions → dependency-audit → Run
workflow**, select the desired branch, and enable **Create or reopen an assigned
test issue to check notification delivery**. A bot creates a separate issue
marked `[Test] Dependency alert notification`, even when the audit is clean.
Confirm it appears in the GitHub inbox and configured mailbox, then close it.
Only default-branch runs update real alert issues; an unmerged fix cannot close
an alert for dependencies still used by `main`.
This verifies the assigned-issue notification route; native Dependabot security
emails and PR watch subscriptions have their own notification settings.

See [GitHub notification configuration](https://docs.github.com/en/subscriptions-and-notifications/get-started/configuring-notifications)
and [Dependabot security notifications](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/manage-your-dependency-security/configure-dependabot-notifications).

## UI Implementation

- Render plain text with escaped Blade output (`{{ }}`) or DOM `textContent`. Labels, errors, notices and tooltips must not interpret user text as HTML.
- Serialize PHP values used by JavaScript with `@js()` or `Illuminate\Support\Js`. Do not interpolate content into JavaScript strings or template literals.
- Raw HTML output (`{!! !!}` or `x-html`) requires an explicit trusted or sanitized content source. Preserve any existing sanitization when refactoring rich-text views.
- Use shared links and buttons for navigation. `App\Support\UiLink` validates static URLs; `SM.safeHref` validates bound URLs. Navigation allows relative URLs, HTTP(S), email, telephone and SMS links, and rejects script protocols. New-tab links must include `rel="noopener"`.
- Use Alpine `x-on:*` or module event listeners instead of native inline `onclick`, `onchange`, etc. Keep the existing CSP nonce and Rocket Loader opt-out on trusted scripts; do not weaken the CSP to accommodate a UI control.
- State changes require the existing server authorization, validation and CSRF protection. Hidden panels, disabled controls and client-side validation do not enforce permissions.
- Review dependency advisories with `composer audit --locked` and `npm audit` when updating dependencies. Audit results do not establish that the application itself is secure.

Reference: [OWASP Cross Site Scripting Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html).
