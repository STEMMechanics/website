# Admin Web Push

Run `composer install`, `php artisan migrate`, and `npm run build` during deployment.
Run `php artisan push:generate-keys` once and securely save the output as
`VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` in the server environment. Set
`VAPID_SUBJECT` to the public HTTPS site URL or a monitored mailto address.
Keep the same keys across releases, then refresh the Laravel config cache and
restart queue workers. The existing `mail` queue handles pushes, and the existing
scheduler triggers reminder and workplan delivery. No paid push relay is needed.

The dashboard prompt appears only when the server has keys configured and the
browser supports push. Not now / close dismisses for the browser session. Don't
remind me again records an off preference for that account and browser. Account
Settings and the admin's own Edit User page provide device controls. Clearing
browser storage creates a new device identity. Other registered devices can be
turned off remotely; enabling a new subscription requires using that device.

On iOS/iPadOS, add the site to the Home Screen and open it there. Browser permission
must be granted separately on each device. Browser-level blocking takes precedence
over saved preferences. Delivery is best effort and email delivery remains enabled.
Notification text deliberately excludes customer and task details. Expired push
subscriptions are disabled. Subscription secrets are encrypted with APP_KEY.

For a deployment smoke test: enable from an admin profile, confirm the browser
permission, trigger a due reminder for that admin, close the tab, and verify the
notification and destination. Check a second device, remote disable, Not now,
Don't remind me again, and denied browser permission. Automated tests do not
contact real browser push services.
