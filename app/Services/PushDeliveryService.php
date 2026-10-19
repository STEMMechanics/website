<?php

namespace App\Services;

use App\Models\PushDevice;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

class PushDeliveryService
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client(['timeout' => 15, 'allow_redirects' => false]);
    }

    public function send(PushDevice $device, string $title, string $url, string $tag, string $body = 'Open STEMMechanics to view the details.'): bool
    {
        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 3600], client: $this->client);
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
