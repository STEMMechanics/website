<?php

namespace Tests\Feature;

use App\Models\SentEmail;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentEmailListTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_details_are_in_a_dialog_and_page_size_is_applied(): void
    {
        $admin = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        for ($i = 0; $i < 12; $i++) {
            SentEmail::create(['recipient' => 'test@example.com', 'mailable_class' => 'TestMail', 'status' => 'failed', 'failed_at' => now(), 'error_message' => '<script>bad()</script>']);
        }
        $this->actingAs($admin)->get(route('admin.server.sent-emails', ['per_page' => 10]))
            ->assertOk()->assertViewHas('emails', fn ($emails) => $emails->count() === 10 && $emails->total() === 12)
            ->assertSee('Email failure details')->assertSee('data-open-dialog=', false)
            ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false)
            ->assertSee('Rows per page');
        $this->get(route('admin.server.sent-emails', ['per_page' => 100000]))
            ->assertOk()->assertViewHas('emails', fn ($emails) => $emails->perPage() === 50);
    }
}
