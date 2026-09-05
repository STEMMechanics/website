<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'devices' => PushDevice::where('user_id', $request->user()->id)->latest()->get()->map(fn (PushDevice $device) => array_merge($device->toArray(), ['can_enable' => $device->subscription !== null])),
            'publicKey' => config('webpush.private_key') ? config('webpush.public_key') : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'enabled' => ['required', 'boolean'],
            'subscription' => ['nullable', 'array'],
            'subscription.endpoint' => ['required_with:subscription', 'url:https', 'max:2048'],
            'subscription.keys.p256dh' => ['required_with:subscription', 'string', 'regex:/^[A-Za-z0-9_-]{87}=?$/'],
            'subscription.keys.auth' => ['required_with:subscription', 'string', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/'],
        ]);
        $device = PushDevice::firstOrNew(['user_id' => $request->user()->id, 'device_id' => $data['device_id']]);
        if ($data['enabled']) {
            abort_unless(config('webpush.public_key') && config('webpush.private_key'), 503, 'Push notifications are not configured yet.');
            $subscription = $data['subscription'] ?? $device->subscription;
            if (! $subscription) {
                throw ValidationException::withMessages(['subscription' => 'Enable notifications from this device first.']);
            }
            $host = parse_url($subscription['endpoint'], PHP_URL_HOST);
            $allowed = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];
            $validHost = in_array($host, $allowed, true) || str_ends_with((string) $host, '.notify.windows.com');
            if (! $validHost || parse_url($subscription['endpoint'], PHP_URL_PORT) || parse_url($subscription['endpoint'], PHP_URL_USER)) {
                throw ValidationException::withMessages(['subscription' => 'Unsupported push service.']);
            }
            $hash = hash('sha256', $subscription['endpoint']);
            if (PushDevice::where('endpoint_hash', $hash)->when($device->exists, fn ($q) => $q->where('id', '!=', $device->id))->exists()) {
                throw ValidationException::withMessages(['subscription' => 'This browser is registered to another account. Turn notifications off there first.']);
            }
            $device->subscription = $subscription;
            $device->endpoint_hash = $hash;
        }
        $device->fill(['name' => $data['name'], 'enabled' => $data['enabled']])->save();

        return response()->json($device);
    }
}
