<?php

namespace App\Services;

class DeploymentConfigurationService
{
    /** @return array<int, array{label: string, status: string, setting: string, instruction: string, blocking: bool}> */
    public function checks(): array
    {
        $checks = [
            'Debug disabled' => ! config('app.debug'),
            'HTTPS application URL' => filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false && parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' && in_array(parse_url(config('app.url'), PHP_URL_PATH), [null, '', '/'], true),
            'Secure session cookie' => (bool) config('session.secure'),
            'HTTP-only session cookie' => (bool) config('session.http_only'),
            'Shared cache store' => in_array(config('cache.stores.'.config('cache.default').'.driver'), ['redis', 'database', 'memcached', 'dynamodb'], true),
            'Durable asynchronous queue' => $this->durableQueue((string) config('queue.default')),
            'Dedicated SMSFlow callback secret (32+ characters)' => strlen((string) config('services.smsflow.webhook_secret')) >= 32
                && config('services.smsflow.webhook_secret') !== config('services.smsflow.api_key'),
            'No proxy wildcard trust' => $this->validProxyRanges(config('security.trusted_proxies', [])),
            'Explicit trusted hosts' => $this->validHosts(),
            'Administrator MFA required' => (bool) config('security.admin_mfa_required'),
            'Generic email login responses' => (bool) config('security.generic_login'),
            'Explicit error recipients' => $this->validRecipients(),
            'Rotating application logs' => $this->rotatingLogs(),
            'Non-production indexing disabled' => app()->environment('production') || ! config('security.indexable'),
            'Canonical redirect enabled in production' => ! app()->environment('production') || config('security.canonical_redirect'),
        ];
        $help = [
            'Debug disabled' => ['APP_DEBUG', 'Set APP_DEBUG=false on deployed sites to prevent diagnostic details appearing in responses.'],
            'HTTPS application URL' => ['APP_URL', 'Set APP_URL to the canonical HTTPS origin, without a path. Configure a valid TLS certificate at the ingress.'],
            'Secure session cookie' => ['SESSION_SECURE_COOKIE', 'Set SESSION_SECURE_COOKIE=true for HTTPS deployments. Ensure the actual TLS proxy addresses are trusted.'],
            'HTTP-only session cookie' => ['SESSION_HTTP_ONLY', 'Keep session.http_only enabled so JavaScript cannot read the session cookie.'],
            'Shared cache store' => ['CACHE_STORE', 'Use a configured shared redis, database, memcached or dynamodb store for throttles, replay protection and snapshots.'],
            'Durable asynchronous queue' => ['QUEUE_CONNECTION', 'Use a configured database, redis, sqs or beanstalkd connection. Run workers for mail and analytics; sync and null are unsuitable for deployment.'],
            'Dedicated SMSFlow callback secret (32+ characters)' => ['SMSFLOW_WEBHOOK_SECRET', 'Generate a dedicated random secret of at least 32 characters, different from SMSFLOW_API_KEY. Configure the same bearer credential or webhook_secret query parameter at SMSFlow. Suppress query credentials in all proxy access logs.'],
            'No proxy wildcard trust' => ['TRUSTED_PROXIES', 'List only the immediate ingress IPs/CIDRs, comma-separated. Do not use wildcards or REMOTE_ADDR. Empty trusts no proxies and is only appropriate for direct connections.'],
            'Explicit trusted hosts' => ['TRUSTED_HOSTS', 'List exact accepted hostnames, including the APP_URL hostname and any redirect aliases. Do not include schemes, paths or wildcards.'],
            'Administrator MFA required' => ['ADMIN_MFA_REQUIRED', 'Set ADMIN_MFA_REQUIRED=true. Administrators must enrol an authenticator; passwordless accounts retain direct TOTP or backup-code sign-in.'],
            'Generic email login responses' => ['GENERIC_LOGIN', 'Set GENERIC_LOGIN=true to keep email-link responses generic. Express sign-in opens the account’s authenticator first, then password, otherwise email verification.'],
            'Explicit error recipients' => ['SECURITY_ERROR_RECIPIENTS', 'Set a comma-separated list of valid, restricted administrator email addresses. Error alerts contain a reference and route, not exception diagnostics.'],
            'Rotating application logs' => ['LOG_CHANNEL / LOG_STACK / LOG_DAILY_DAYS', 'Use LOG_CHANNEL=daily or LOG_STACK=daily, with LOG_DAILY_DAYS=14. Restrict private files to 0640 and directories to 0750; confirm central retention separately for remote logging.'],
            'Non-production indexing disabled' => ['SITE_INDEXABLE', 'Set SITE_INDEXABLE=false on staging and other non-production deployments. Also apply noindex at the staging ingress.'],
            'Canonical redirect enabled in production' => ['CANONICAL_HOST_REDIRECT', 'Set CANONICAL_HOST_REDIRECT=true in production and configure APP_URL and trusted host aliases. Signed and token-bearing links are not redirected.'],
        ];
        $rows = [];
        foreach ($checks as $label => $passed) {
            $rows[] = ['label' => $label, 'status' => $passed ? 'pass' : 'fail',
                'setting' => $help[$label][0], 'instruction' => $help[$label][1], 'blocking' => true];
        }
        foreach ($rows as &$row) {
            if ($row['label'] === 'Rotating application logs' && ! $checks['Rotating application logs'] && $this->remoteLogging()) {
                $row['status'] = 'review';
            }
        }
        unset($row);
        if (config('security.trusted_proxies', []) === []) {
            foreach ($rows as &$row) {
                if ($row['label'] === 'No proxy wildcard trust') {
                    $row['status'] = 'review';
                }
            }
            unset($row);
        }
        $extra = [
            ['SMSFlow outbound callback URL', $this->callbackStatus(), 'SMSFLOW_CALLBACK_URL', 'Configure the intended HTTPS /webhooks/smsflow URL for outgoing-message callbacks. If using a query credential, webhook_secret must match SMSFLOW_WEBHOOK_SECRET. Otherwise verify that the provider sends the bearer header. An unset URL may be intentional when callbacks are configured directly at the provider.', false],
            ['Analytics queue connection', $this->durableQueue((string) (config('analytics.queue_connection') ?: config('queue.default'))) ? 'pass' : 'fail', 'ANALYTICS_QUEUE_CONNECTION', 'Use a durable configured queue connection, or leave unset to use QUEUE_CONNECTION. Run a worker listening on the analytics queue.', true],
            ['Analytics migration', $this->analyticsSchemaReady() ? 'pass' : 'fail', 'Database migrations', 'Run php artisan migrate --force before restarting workers. Analytics needs the event_uuid column for retry protection.', true],
            ['Cached configuration', app()->configurationIsCached() ? 'pass' : 'review', 'Configuration cache', 'After changing environment settings, run php artisan config:cache and restart queue workers. This page reports effective loaded configuration, not raw .env contents. Local development may intentionally leave configuration uncached.', false],
            ['Dashboard snapshots', (int) config('analytics.dashboard_snapshot_seconds') > 0 ? 'pass' : 'review', 'DASHBOARD_SNAPSHOT_SECONDS', 'Set to 300 to serve five-minute dashboard snapshots. Zero intentionally disables cached figures. Schedule analytics:snapshot via the Laravel scheduler.', false],
            ['Request profiling', 'review', 'PROFILE_REQUESTS', config('analytics.profile_requests') ? 'Profiling is enabled. Use representative dashboard/report/export requests, inspect route-only timings, then disable it after measuring.' : 'Profiling is disabled. Temporarily set PROFILE_REQUESTS=true to measure representative dashboard/report/export requests, then disable it again.', false],
            ['CSP report-only rollout', config('security.csp_report_only') ? 'pass' : 'review', 'CSP_REPORT_ONLY', 'Set CSP_REPORT_ONLY=true to collect bounded script-policy reports. Resolve the inventory of inline scripts and handlers before enforcing script restrictions.', false],
            ['Workers and scheduler', 'review', 'Process supervisor / cron', 'Verify mail and analytics workers are running and queues drain. Schedule php artisan schedule:run every minute; check snapshots refresh and failed jobs are pruned. Configuration alone cannot establish process health.', false],
            ['Ingress, TLS and asset caching', 'review', 'Reverse proxy / CDN / firewall', 'Apply deployment/nginx-location-controls.conf for immutable hashed assets and service-worker revalidation. Keep private HTML/downloads uncached, strip forwarded headers at ingress, restrict origin access, and verify TLS renewal and canonical redirects externally.', false],
            ['Backups and recovery', 'review', 'Backup storage / recovery procedures', 'Keep encrypted backups outside public/. Restore database and files to a disposable environment and record the result. Verify administrator recovery and retain unused backup codes offline.', false],
            ['Private files and historical logs', 'review', 'File permissions / retention', 'Restrict private files to 0640 and directories to 0750 with the correct application owner/group. Review historical logs and failed-job retention separately; this page does not inspect file contents or rewrite historical data.', false],
            ['SMSFlow provider delivery', 'review', 'Provider webhook configuration', 'Perform an authenticated provider test callback. Confirm valid callbacks arrive and missing/incorrect credentials return 401. A configured local secret does not prove the provider uses it.', false],
        ];
        foreach ($extra as [$label, $status, $setting, $instruction, $blocking]) {
            $rows[] = compact('label', 'status', 'setting', 'instruction', 'blocking');
        }
        return $rows;
    }
    private function durableQueue(string $connection): bool
    {
        return in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true);
    }

    private function validProxyRanges(array $ranges): bool
    {
        foreach ($ranges as $range) {
            $parts = explode('/', (string) $range, 2);
            if (filter_var($parts[0], FILTER_VALIDATE_IP) === false || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] > (str_contains($parts[0], ':') ? 128 : 32)))) {
                return false;
            }
        }
        return true;
    }

    private function validHosts(): bool
    {
        $hosts = config('security.trusted_hosts', []);
        if (! in_array(parse_url(config('app.url'), PHP_URL_HOST), $hosts, true)) {
            return false;
        }
        foreach ($hosts as $host) {
            if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
                return false;
            }
        }
        return true;
    }

    private function validRecipients(): bool
    {
        $recipients = preg_split('/[;,]+/', (string) config('security.error_recipients')) ?: [];
        return count($recipients) > 0 && count(array_filter($recipients, fn ($email) => filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false)) === count($recipients);
    }

    private function rotatingLogs(): bool
    {
        $channels = config('logging.default') === 'stack' ? config('logging.channels.stack.channels', []) : [config('logging.default')];
        if ($channels === []) {
            return false;
        }
        foreach ($channels as $channel) {
            if (config('logging.channels.'.$channel.'.driver') !== 'daily' || (int) config('logging.channels.'.$channel.'.days') < 1) {
                return false;
            }
        }
        return true;
    }

    private function remoteLogging(): bool
    {
        $channels = config('logging.default') === 'stack' ? config('logging.channels.stack.channels', []) : [config('logging.default')];
        $hasRemote = false;
        foreach ($channels as $channel) {
            $driver = config('logging.channels.'.$channel.'.driver');
            if ($driver === 'daily' && (int) config('logging.channels.'.$channel.'.days') > 0) {
                continue;
            }
            if (! in_array($driver, ['monolog', 'slack', 'syslog', 'errorlog'], true)) {
                return false;
            }
            $hasRemote = true;
        }
        return $hasRemote;
    }

    private function callbackStatus(): string
    {
        $url = trim((string) config('services.smsflow.callback_url'));
        if ($url === '') {
            return 'review';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)
            || parse_url($url, PHP_URL_PATH) !== '/webhooks/smsflow') {
            return 'fail';
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (! isset($query['webhook_secret'])) {
            return 'review';
        }
        $secret = (string) config('services.smsflow.webhook_secret');
        return is_string($query['webhook_secret']) && strlen($secret) >= 32 && hash_equals($secret, $query['webhook_secret']) ? 'pass' : 'fail';
    }

    private function analyticsSchemaReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('analytics_events', 'event_uuid');
        } catch (\Throwable) {
            return false;
        }
    }

}
