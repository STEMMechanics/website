<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class SecretExposureTest extends TestCase
{
    public function test_repository_does_not_contain_hard_coded_secret_values(): void
    {
        $findings = [];

        foreach ($this->filesToScan() as $file) {
            $contents = File::get($file->getRealPath());
            $filePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            foreach (self::secretPatterns() as $label => $pattern) {
                if (preg_match($pattern, $contents) !== 1) {
                    continue;
                }

                $findings[] = $filePath.': '.$label;
            }

            foreach ($this->envSecretFindings($contents) as $finding) {
                $findings[] = $filePath.': '.$finding;
            }
        }

        $findings = array_values(array_unique($findings));

        $this->assertSame(
            [],
            $findings,
            'Potential secret exposure found: '.implode(', ', $findings)
        );
    }

    /**
     * @return array<string, string>
     */
    private static function secretPatterns(): array
    {
        return [
            'private key block' => '/-----BEGIN [A-Z ]+PRIVATE KEY-----/m',
            'AWS access key' => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
            'GitHub token' => '/\b(?:ghp|github_pat)_[A-Za-z0-9_]{20,}\b/',
            'Slack token' => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
            'Google API key' => '/\bAIza[0-9A-Za-z_-]{35}\b/',
            'Stripe secret key' => '/\bsk_(?:live|test)_[A-Za-z0-9]{16,}\b/',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function envSecretFindings(string $contents): array
    {
        $findings = [];
        $secretNames = [
            'APP_KEY',
            'ALTCHA_HMAC_KEY',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'CLOUDFLARE_API_KEY',
            'DB_PASSWORD',
            'FLARE_KEY',
            'LIVEKIT_API_KEY',
            'LIVEKIT_API_SECRET',
            'MAILGUN_SECRET',
            'MAIL_PASSWORD',
            'POSTMARK_TOKEN',
            'PUSHER_APP_SECRET',
            'REDIS_PASSWORD',
            'SLACK_BOT_USER_OAUTH_TOKEN',
            'SQUARE_ACCESS_TOKEN',
            'SQUARE_WEBHOOK_SIGNATURE_KEY',
        ];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (! is_string($line) || $line === '') {
                continue;
            }

            if (! preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $name = (string) $matches[1];
            if (! in_array($name, $secretNames, true)) {
                continue;
            }

            $value = trim((string) $matches[2]);
            if (
                $value === ''
                || strtolower($value) === 'null'
                || $value === '""'
                || $value === "''"
                || preg_match('/^\$\{[A-Z0-9_]+\}$/', $value) === 1
                || preg_match('/^[\"\']\$\{[A-Z0-9_]+\}[\"\']$/', $value) === 1
            ) {
                continue;
            }

            $findings[] = 'hard-coded env secret';
        }

        return $findings;
    }

    /**
     * @return array<int, \SplFileInfo>
     */
    private function filesToScan(): array
    {
        $files = [];

        foreach ([
            'app',
            'bootstrap',
            'config',
            'database',
            'resources',
            'routes',
        ] as $directory) {
            $finder = Finder::create()
                ->files()
                ->in(base_path($directory))
                ->name('/\.(?:php|js|jsx|css|md|json|ya?ml|http|xml|blade\.php)$/i');

            foreach ($finder as $file) {
                $files[] = $file;
            }
        }

        foreach ([
            'README.md',
            'SECURITY.md',
            'composer.json',
            'package.json',
            'phpunit.xml',
            'pint.json',
            'qodana.yaml',
            'renovate.json',
            'renovate-config.json',
            'api.http',
            'vite.config.js',
            '.env.example',
            'artisan',
        ] as $file) {
            $path = base_path($file);

            if (is_file($path)) {
                $files[] = new \SplFileInfo($path);
            }
        }

        return $files;
    }
}
