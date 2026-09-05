<?php

namespace App\Jobs;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\PushDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $userId, public string $title, public string $url, public string $tag)
    {
        $this->onQueue('mail');
    }

    public function handle(PushDeliveryService $delivery): void
    {
        $user = User::find($this->userId);
        if (! $user?->isAdmin() || ! config('webpush.private_key')) {
            return;
        }
        foreach (PushDevice::where('user_id', $this->userId)->where('enabled', true)->get() as $device) {
            if (! $device->subscription) {
                continue;
            }
            $delivery->send($device, $this->title, $this->url, $this->tag);
        }
    }
}
