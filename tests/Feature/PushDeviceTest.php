<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);

        return $user;
    }

    public function test_only_admins_can_manage_devices(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('admin.push-devices.index'))->assertForbidden();
    }

    public function test_device_choices_are_separate_and_private_to_each_admin(): void
    {
        $first = $this->admin();
        $second = $this->admin();
        $id = (string) Str::uuid();
        $data = ['device_id' => $id, 'name' => 'Phone', 'enabled' => false];
        $this->actingAs($first)->putJson(route('admin.push-devices.update'), $data)->assertOk();
        $this->getJson(route('admin.push-devices.index'))->assertJsonCount(1, 'devices')->assertJsonPath('devices.0.enabled', false);
        $this->actingAs($second)->getJson(route('admin.push-devices.index'))->assertJsonCount(0, 'devices');
        $this->putJson(route('admin.push-devices.update'), $data)->assertOk();
        $this->assertDatabaseCount('push_devices', 2);
    }

    public function test_subscription_is_encrypted_and_not_returned_and_can_be_disabled(): void
    {
        config(['webpush.public_key' => 'public', 'webpush.private_key' => 'private']);
        $this->actingAs($this->admin());
        $data = ['device_id' => (string) Str::uuid(), 'name' => 'Phone', 'enabled' => true, 'subscription' => [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
            'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)],
        ]];
        $this->putJson(route('admin.push-devices.update'), $data)->assertOk()->assertJsonMissingPath('subscription');
        $device = PushDevice::sole();
        $this->assertStringNotContainsString('fcm.googleapis.com', $device->getRawOriginal('subscription'));
        $this->assertTrue($device->enabled);
        unset($data['subscription']);
        $data['enabled'] = false;
        $this->putJson(route('admin.push-devices.update'), $data)->assertOk()->assertJsonPath('enabled', false);
    }

    public function test_arbitrary_push_endpoints_are_rejected(): void
    {
        config(['webpush.public_key' => 'public', 'webpush.private_key' => 'private']);
        $this->actingAs($this->admin())->putJson(route('admin.push-devices.update'), [
            'device_id' => (string) Str::uuid(), 'name' => 'Phone', 'enabled' => true,
            'subscription' => ['endpoint' => 'https://127.0.0.1/private', 'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('push_devices', 0);
    }
}
