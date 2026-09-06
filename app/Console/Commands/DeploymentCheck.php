<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeploymentCheck extends Command
{
    protected $signature = 'security:deployment-check';
    protected $description = 'Validate production security configuration without printing credentials';

    public function handle(): int
    {
        $failed = false;
        foreach (app(\App\Services\DeploymentConfigurationService::class)->checks() as $check) {
            $this->line(strtoupper($check['status']).' '.$check['label']);
            $failed = $failed || ($check['blocking'] && $check['status'] === 'fail');
        }
        $this->comment('Network firewall, provider callback delivery, TLS renewal and backup restoration require independent deployment verification.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
