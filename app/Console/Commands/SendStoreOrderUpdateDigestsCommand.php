<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Mail\StoreOrderAdminUpdateDigest;
use App\Mail\StoreOrderCustomerUpdateDigest;
use App\Services\StoreOrderUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SendStoreOrderUpdateDigestsCommand extends Command
{
    protected $signature = 'store:orders:send-update-digests';

    protected $description = 'Queue nightly batched customer and admin digests for store order fulfilment updates';

    public function handle(StoreOrderUpdateService $updates): int
    {
        if (! Schema::hasTable('store_order_updates')) {
            return self::SUCCESS;
        }

        $digestDateLabel = now()->format('F jS Y');
        $customerDigestCount = 0;

        foreach ($updates->pendingCustomerDigests() as $payload) {
            dispatch(new SendEmail(
                (string) $payload['recipient_email'],
                new StoreOrderCustomerUpdateDigest(
                    (string) $payload['recipient_name'],
                    $digestDateLabel,
                    $payload['orders'],
                )
            ))->onQueue('mail');

            $updates->markCustomerDigestQueued($payload['event_ids']);
            $customerDigestCount++;
        }

        $adminRecipients = $updates->adminRecipients();
        $adminPayload = $updates->pendingAdminDigest();
        $adminDigestCount = 0;

        if ($adminPayload !== null && $adminRecipients !== []) {
            foreach ($adminRecipients as $recipient) {
                dispatch(new SendEmail(
                    $recipient,
                    new StoreOrderAdminUpdateDigest($digestDateLabel, $adminPayload['orders'])
                ))->onQueue('mail');
            }

            $updates->markAdminDigestQueued($adminPayload['event_ids']);
            $adminDigestCount = count($adminRecipients);
        }

        $this->info('Queued '.$customerDigestCount.' customer digest(s) and '.$adminDigestCount.' admin digest(s).');

        return self::SUCCESS;
    }
}
