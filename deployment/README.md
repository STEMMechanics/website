# Deployment templates

This directory contains optional configuration examples:

- `analytics-worker.conf`: process-manager template for an analytics queue worker.
- `nginx-location-controls.conf`: example Nginx location rules.

Adapt paths and settings to your own environment before use. These examples do
not describe the configuration of any running STEMMechanics infrastructure.
Keep environment-specific runbooks, credentials and infrastructure details
outside the public repository.

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
