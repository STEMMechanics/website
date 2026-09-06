<?php

namespace App\Services;

use App\Models\PushDevice;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushDeliveryService
{
    public function send(PushDevice $device, string $title, string $url, string $tag, string $body = 'Open STEMMechanics to view the details.'): bool
    {
        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 3600], 15, ['allow_redirects' => false]);
        $report = $push->sendOneNotification(Subscription::create($device->subscription), json_encode([
            'title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag,
        ], JSON_THROW_ON_ERROR));
        if ($report->isSubscriptionExpired()) {
            $device->update(['enabled' => false, 'subscription' => null, 'endpoint_hash' => null]);
        } elseif (! $report->isSuccess()) {
            logger()->warning('Push delivery failed', ['device_id' => $device->id, 'status' => $report->getResponse()?->getStatusCode()]);
        }

        return $report->isSuccess();
    }
}
