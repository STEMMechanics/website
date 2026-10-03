# Deployment templates

This directory contains optional configuration examples:

- `analytics-worker.conf`: process-manager template for an analytics queue worker.
- `nginx-location-controls.conf`: example Nginx location rules.

Adapt paths and settings to your own environment before use. These examples do
not describe the configuration of any running STEMMechanics infrastructure.
Keep environment-specific runbooks, credentials and infrastructure details
outside the public repository.

## PHP version header at the origin

The production image loads `.docker/production/php.ini`, which sets
`expose_php = Off`. Rebuild and restart PHP-FPM after deploying that image.
The Nginx file in this directory is an optional example and does not identify
the live origin configuration. Apply the matching directive in the actual
origin's existing PHP location:

- Nginx passing requests directly to PHP-FPM: `fastcgi_hide_header X-Powered-By;`
- Nginx proxying to an HTTP upstream: `proxy_hide_header X-Powered-By;`

After deployment, check a public response and confirm it has no
`X-Powered-By` header. The response must be checked at the public edge as well
as directly at the origin when that access is available.

```sh
curl -sS -D - -o /dev/null https://www.stemmechanics.com.au/ | grep -i '^X-Powered-By:'
```

The command should produce no matching header.

## CSP rollout

The application inventories same-origin Vite and application scripts, the
local Altcha asset, and Square's production and sandbox script hosts. It emits
the reviewed policy through `Content-Security-Policy-Report-Only` by default.
Keep `CSP_REPORT_ONLY=true` while browser testing public pages, checkout,
ticket payment, editors and administration. Set `CSP_ENFORCE=true` only after
those reports and flows have been reviewed. The policy excludes inline event
attributes and dynamic evaluation; migrate any reported legacy handlers and
confirm Alpine and checkout behavior before enabling enforcement.

## SMSFlow callback authentication

The webhook checks `Authorization: Bearer <SMSFLOW_WEBHOOK_SECRET>` first. The
public SMSFlow API documentation specifies bearer headers for API requests but
does not document the headers sent on callback requests. Confirm the callback
provider setting before disabling the temporary query fallback:

1. Configure the provider callback URL without `webhook_secret` and have it
   send the bearer header.
2. Send a provider test callback and confirm it returns HTTP 200; confirm a
   missing or incorrect credential returns HTTP 401.
3. Set `SMSFLOW_ALLOW_QUERY_SECRET=false` and remove any query secret from the
   provider URL.

While the fallback is enabled, configure the live ingress to suppress access
logs for the exact `/webhooks/smsflow` location or use an access-log format
that omits query strings. The repository example does not describe the live
ingress and cannot apply that operational setting.

## Visitor IP addresses behind a proxy

Set `TRUSTED_PROXIES` to the comma-separated IP addresses or CIDRs of the trusted
ingress proxies seen by PHP as `REMOTE_ADDR`. Wildcards such as `*` are ignored.
The proxy must set or safely append `X-Forwarded-For` with the original client IP;
the application uses that header only when the immediate connection is trusted.
For a chain of proxies, configure each trusted hop so the nearest untrusted
address is correctly identified as the client.

After changing the deployment environment, rebuild Laravel's configuration cache
with `php artisan config:cache` and restart any long-running application workers.
The online visitors list refreshes the IP when the visitor next loads a tracked
page; already cached proxy addresses cannot be recovered retroactively.
