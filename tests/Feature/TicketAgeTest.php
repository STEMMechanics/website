<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\TicketReissueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reissuing_preserves_age_and_allows_it_to_be_changed_or_cleared(): void
    {
        $ticket = Ticket::factory()->create(['age' => 8]);
        $service = app(TicketReissueService::class);
        $details = $ticket->only(['firstname', 'surname', 'email', 'phone']);
        $details['phone'] = '0400 111 222';
        $replacement = $service->reissue($ticket, $details)['new_ticket'];
        $this->assertSame(8, $replacement->fresh()->age);

        $replacement = $service->reissue($replacement, $details + ['age' => 0])['new_ticket'];
        $this->assertSame(0, $replacement->fresh()->age);
        $replacement = $service->reissue($replacement, $details + ['age' => null])['new_ticket'];
        $this->assertNull($replacement->fresh()->age);
        $this->assertFalse($service->hasAttendeeChanges($replacement, $details));
    }
}
