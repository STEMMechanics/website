<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_event_creates_audit_log_entry(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login',
            'auditable_type' => User::class,
            'auditable_id' => (string) $user->id,
            'actor_user_id' => (string) $user->id,
        ]);

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('web', $log->new_values['guard'] ?? null);
        $this->assertFalse((bool) ($log->new_values['remember'] ?? true));
    }
}

