<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeysCommand extends Command
{
    protected $signature = 'push:generate-keys';

    protected $description = 'Generate VAPID keys to store in the server environment';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->info('Store these securely in the server environment. Keep existing keys once devices have subscribed.');

        return self::SUCCESS;
    }
}
