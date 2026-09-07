<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\DeploymentConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_configuration_flags_missing_settings_without_exposing_keys(): void
    {
        config(['webpush.public_key' => null, 'webpush.private_key' => '', 'webpush.subject' => 'invalid']);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        foreach (['Push notification public key', 'Push notification private key', 'Push notification contact'] as $label) {
            $this->assertSame('fail', $checks[$label]['status']);
            $this->assertFalse($checks[$label]['blocking']);
        }
        config(['webpush.public_key' => 'test-public-secret', 'webpush.private_key' => 'test-private-secret', 'webpush.subject' => 'mailto:admin@example.com']);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('pass', $checks['Push notification public key']['status']);
        $this->assertSame('pass', $checks['Push notification private key']['status']);
        $this->assertSame('pass', $checks['Push notification contact']['status']);
        $this->assertStringNotContainsString('test-public-secret', $checks->toJson());
        $this->assertStringNotContainsString('test-private-secret', $checks->toJson());
        config(['webpush.subject' => 'https://example.com']);
        $this->assertSame('pass', collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label')['Push notification contact']['status']);
    }

    public function test_configuration_checks_validate_values_without_disclosing_secrets(): void
    {
        config([
            'services.smsflow.webhook_secret' => 'short-private-secret',
            'services.smsflow.api_key' => 'private-api-key',
            'security.error_recipients' => 'private-invalid-recipient',
            'security.trusted_proxies' => ['REMOTE_ADDR'],
            'security.trusted_hosts' => ['*.example.com'],
            'queue.default' => 'missing-connection',
        ]);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        foreach (['Dedicated SMSFlow callback secret (32+ characters)', 'Explicit error recipients', 'No proxy wildcard trust', 'Explicit trusted hosts', 'Durable asynchronous queue'] as $label) {
            $this->assertSame('fail', $checks[$label]['status']);
            $this->assertNotEmpty($checks[$label]['instruction']);
        }
        $encoded = json_encode($checks, JSON_THROW_ON_ERROR);
        foreach (['short-private-secret', 'private-api-key', 'private-invalid-recipient'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        config(['services.smsflow.webhook_secret' => str_repeat('s', 40), 'security.error_recipients' => 'security@example.com', 'security.trusted_proxies' => ['192.0.2.0/24', '2001:db8::/32']]);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('pass', $checks['Dedicated SMSFlow callback secret (32+ characters)']['status']);
        $this->assertSame('pass', $checks['No proxy wildcard trust']['status']);
        $this->assertSame('review', $checks['Workers and scheduler']['status']);
        $this->assertSame('pass', $checks['Analytics migration']['status']);
        config(['services.smsflow.callback_url' => rtrim(config('app.url'), '/').'/webhooks/smsflow?webhook_secret='.str_repeat('s', 40)]);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('pass', $checks['SMSFlow outbound callback URL']['status']);
        $this->assertStringNotContainsString(str_repeat('s', 40), json_encode($checks, JSON_THROW_ON_ERROR));
        config(['services.smsflow.callback_url' => 'https://other.example/webhooks/smsflow?webhook_secret=wrong']);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('fail', $checks['SMSFlow outbound callback URL']['status']);
        config(['logging.default' => 'stack', 'logging.channels.stack.channels' => ['daily', 'slack']]);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('review', $checks['Rotating application logs']['status']);
        config(['logging.channels.stack.channels' => ['missing-channel']]);
        $checks = collect(app(DeploymentConfigurationService::class)->checks())->keyBy('label');
        $this->assertSame('fail', $checks['Rotating application logs']['status']);
    }

    public function test_admin_site_info_shows_statuses_and_remediation_but_not_secret_values(): void
    {
        config(['services.smsflow.webhook_secret' => 'unique-private-callback-secret', 'services.smsflow.api_key' => 'unique-private-api-key']);
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->get(route('admin.server.index'))->assertOk()
            ->assertSee('Configuration')->assertSee('Needs attention')->assertSee('Review needed')
            ->assertSee('SMSFLOW_WEBHOOK_SECRET')->assertSee('php artisan config:cache')
            ->assertDontSee('unique-private-callback-secret')->assertDontSee('unique-private-api-key');
    }

    public function test_site_info_remains_administrator_only(): void
    {
        $this->get(route('admin.server.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.server.index'))->assertForbidden();
    }

    public function test_deployment_command_uses_the_same_checks_and_fails_for_blocking_findings(): void
    {
        $this->mock(DeploymentConfigurationService::class)->shouldReceive('checks')->once()->andReturn([
            ['label' => 'Example setting', 'status' => 'fail', 'setting' => 'EXAMPLE', 'instruction' => 'Fix it.', 'blocking' => true],
        ]);
        $this->artisan('security:deployment-check')->expectsOutput('FAIL Example setting')->assertFailed();
    }

    public function test_manual_review_does_not_claim_verification_or_block_the_release(): void
    {
        $this->mock(DeploymentConfigurationService::class)->shouldReceive('checks')->once()->andReturn([
            ['label' => 'Worker health', 'status' => 'review', 'setting' => 'Workers', 'instruction' => 'Verify processes.', 'blocking' => false],
        ]);
        $this->artisan('security:deployment-check')->expectsOutput('REVIEW Worker health')->assertSuccessful();
    }
}
