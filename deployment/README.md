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

## Large file backup imports

The admin file-backup restore flow uploads ZIP archives as sequential 8 MiB
requests, so the complete archive does not need to fit PHP's multipart upload
limit or a single web request. Configure the actual ingress/reverse proxy to
accept request bodies of at least 9 MiB (10 MiB is a practical setting), and set
PHP `post_max_size` above 8 MiB. `upload_max_filesize` does not limit these raw
chunk requests. The Nginx file in this directory is only an example; apply the
limit to the live origin and any CDN/proxy in front of it.

Uploads are staged on the private local disk and the queue worker unpacks the
archive into the file-backup store. The web process and queue worker therefore
need the same `storage/app` files, and the server needs free space for both the
uploaded ZIP and its expanded contents. The importer checks free space before
upload and unpacking and leaves a 256 MiB reserve. Keep the queue worker's
execution timeout and queue visibility/retry-after setting aligned: the
`RunServerBackup` job can run for up to one hour, so its queue visibility must
exceed 3,600 seconds. The database, Redis and Beanstalk defaults are 3,900
seconds; if an environment variable overrides them, keep it above the job
timeout. For SQS, set the queue's visibility timeout above the job timeout.
