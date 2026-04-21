<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuoteIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_quote_index_renders_mobile_cards_and_quote_details(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Quinn',
            'surname' => 'Taylor',
            'email' => 'quinn.taylor@example.com',
        ]);

        Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-100001',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => '2026-04-01',
            'title' => 'Custom workshop proposal',
            'total_amount' => 123.45,
            'subtotal_amount' => 112.23,
            'gst_amount' => 11.22,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.quote.index'));

        $response->assertOk();
        $response->assertSee('space-y-4 md:hidden', false);
        $response->assertSeeText('Q-100001');
        $response->assertSeeText('Custom workshop proposal');
        $response->assertSeeText('Quinn Taylor');
        $response->assertSeeText('Apr 1, 2026');
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
