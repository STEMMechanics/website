<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendAdminPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $userId, public string $title, public string $url, public string $tag)
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user?->isAdmin() || ! config('webpush.private_key')) {
            return;
        }
        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 3600], 15, ['allow_redirects' => false]);
        foreach (PushDevice::where('user_id', $this->userId)->where('enabled', true)->get() as $device) {
            if (! $device->subscription) {
                continue;
            }
            $report = $push->sendOneNotification(Subscription::create($device->subscription), json_encode([
                'title' => $this->title,
                'body' => 'Open STEMMechanics to view the details.',
                'url' => $this->url,
                'tag' => $this->tag,
            ], JSON_THROW_ON_ERROR));
            if ($report->isSubscriptionExpired()) {
                $device->update(['enabled' => false, 'subscription' => null, 'endpoint_hash' => null]);
            } elseif (! $report->isSuccess()) {
                logger()->warning('Push delivery failed', ['device_id' => $device->id, 'status' => $report->getResponse()?->getStatusCode()]);
            }
        }
    }
}
