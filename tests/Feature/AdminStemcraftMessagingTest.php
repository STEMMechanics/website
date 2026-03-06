<?php

namespace Tests\Feature;

use App\Models\MinecraftMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStemcraftMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_messaging_page_with_placeholder_for_unfilterable_blocked_messages(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        MinecraftMessage::query()->create([
            'occurred_at' => now()->subMinute(),
            'message_type' => 'sign',
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'server_name' => 'survival',
            'world' => 'world',
            'x' => 10,
            'y' => 64,
            'z' => -5,
            'raw_message' => 'This includes fck directly.',
            'filtered_message' => null,
            'passed' => false,
            'failure_reason' => 'custom_regex',
            'failure_detail' => '\bfck\b',
            'context' => ['side' => 'front'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.messages.index'));

        $response->assertOk();
        $response->assertSeeText('Player messaging');
        $response->assertSeeText('[Message blocked by moderation filter]');
        $response->assertSeeText('This includes fck directly.');
        $response->assertSee('stemcraft\\/messages\\/snapshot', false);
    }

    public function test_admin_messaging_snapshot_returns_rendered_results_html(): void
    {
        $admin = User::factory()->create([
            'firstname' => 'Admin',
            'surname' => 'User',
        ]);
        $admin->groups()->create(['slug' => 'admin']);

        $message = MinecraftMessage::query()->create([
            'occurred_at' => now()->subMinute(),
            'message_type' => 'chat',
            'platform' => 'java',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'username' => 'PlayerOne',
            'server_name' => 'survival',
            'world' => 'world',
            'x' => 10,
            'y' => 64,
            'z' => -5,
            'raw_message' => 'Hello from chat',
            'filtered_message' => null,
            'passed' => true,
            'failure_reason' => null,
            'failure_detail' => null,
            'context' => ['channel' => 'global'],
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.stemcraft.messages.snapshot', ['message_type' => 'chat']));

        $response->assertOk();
        $response->assertJsonStructure(['resultsHtml']);
        $this->assertStringContainsString('PlayerOne', (string) $response->json('resultsHtml'));
        $this->assertStringContainsString('Hello from chat', (string) $response->json('resultsHtml'));
        $this->assertStringContainsString(
            'data-refresh-key="message-'.$message->id.'-desktop-raw"',
            (string) $response->json('resultsHtml')
        );
    }
}
