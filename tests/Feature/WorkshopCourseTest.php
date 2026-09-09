<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Jobs\SendWorkshopWelcome;
use App\Mail\TicketOrderConfirmation;
use App\Mail\WorkshopWelcome;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\WorkshopAllocation;
use App\Services\WorkshopCourseSettings;
use App\Services\WorkshopTicketOrderEmailService;
use App\Services\WorkshopWelcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkshopCourseTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Workshop
    {
        $ticket = Ticket::factory()->create(['email' => 'parent@example.test']);
        $workshop = $ticket->workshop;
        $sessions = [];
        for ($i = 0; $i < 8; $i++) {
            $start = now()->addDays(2 + 7 * $i)->startOfHour();
            $sessions[] = ['id' => (string) Str::uuid(), 'label' => 'Session '.($i + 1), 'starts_at' => $start->format('Y-m-d\TH:i'), 'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i')];
        }
        $workshop->update([
            'format' => 'course', 'course_sessions' => $sessions, 'type' => 'online', 'location_id' => null, 'registration' => 'tickets',
            'starts_at' => $sessions[0]['starts_at'], 'ends_at' => $sessions[7]['ends_at'], 'max_tickets' => 12,
            'welcome_enabled' => true, 'welcome_subject' => 'Welcome to the course', 'welcome_body' => 'Your Zoom link: https://example.test/join',
            'welcome_send_at' => now()->subDay(),
        ]);
        $this->actingAs($ticket->user);
        UserGroup::firstOrCreate(['user_id' => $ticket->user_id, 'slug' => 'admin']);

        return $workshop->fresh();
    }

    public function test_eight_week_course_uses_eight_teaching_hours_and_displays_all_sessions(): void
    {
        $course = $this->course();
        $this->assertSame(8.0, $course->teachingHours());
        $this->assertCount(8, $course->courseScheduleDisplayLines());
        $this->assertSame('8 hours', $course->workshopDurationLabel());
        $this->get(route('workshop.show', $course))->assertOk()->assertSee($course->courseScheduleDisplayLines()[7])->assertDontSee('Session 8');
        $this->get(route('admin.workshop.edit', $course))->assertOk()->assertSee('Course sessions')->assertSee('Welcome email');
        $this->get(route('admin.workshop.attendance', $course))->assertOk()->assertSee('Course session');
        $this->assertStringContainsString($course->courseScheduleDisplayLines()[7], (new WorkshopWelcome($course))->render());
    }

    public function test_schedule_validation_rejects_overlaps_outside_range_and_empty_courses(): void
    {
        $course = $this->course();
        $valid = ['format' => 'course', 'starts_at' => $course->starts_at, 'ends_at' => $course->ends_at, 'course_sessions' => $course->course_sessions];
        $service = app(WorkshopCourseSettings::class);
        $this->assertCount(8, $service->validated(new Request($valid), $course)['course_sessions']);
        $overlap = $course->course_sessions;
        $overlap[1]['starts_at'] = $overlap[0]['starts_at'];
        $outside = $course->course_sessions;
        $outside[0]['starts_at'] = now()->subDay()->toDateTimeString();
        foreach ([$overlap, $outside, []] as $sessions) {
            try {
                $service->validated(new Request(array_replace($valid, ['course_sessions' => $sessions])), $course);
                $this->fail('Invalid schedule was accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_welcome_deduplicates_active_contacts_and_catches_late_bookings(): void
    {
        Queue::fake();
        $course = $this->course();
        $ticket = $course->tickets->first();
        $ticket->user->update(['email' => 'parent@example.test']);
        foreach ([Ticket::STATUS_HOLD, Ticket::STATUS_CANCELLED, Ticket::STATUS_RELEASED, Ticket::STATUS_REISSUED] as $status) {
            Ticket::factory()->create(['workshop_id' => $course->id, 'status' => $status, 'email' => 'inactive'.$status.'@example.test']);
        }
        Ticket::factory()->create(['workshop_id' => $course->id, 'email' => 'PARENT@example.test']);
        $service = app(WorkshopWelcomeService::class);
        $this->assertSame(1, $service->queueDue($course));
        $this->assertSame(0, $service->queueDue($course));
        Queue::assertPushed(SendWorkshopWelcome::class, 1);
        Ticket::factory()->create(['workshop_id' => $course->id, 'status' => Ticket::STATUS_PENDING_XFER, 'email' => 'late@example.test']);
        $this->assertSame(1, $service->queueDue($course));
        $course->update(['welcome_body' => 'Updated link']);
        $this->assertSame(0, $service->queueDue($course));
        $this->travel(3)->days();
        Ticket::factory()->create(['workshop_id' => $course->id, 'email' => 'too-late@example.test']);
        $this->assertSame(0, $service->queueDue($course));
    }

    public function test_delivery_rechecks_cancelled_tickets_and_workshops_and_does_not_resend(): void
    {
        Queue::fake();
        Mail::fake();
        $course = $this->course();
        $course->tickets->first()->user->update(['email' => 'parent@example.test']);
        $service = app(WorkshopWelcomeService::class);
        $service->queueDue($course);
        $id = DB::table('workshop_welcome_deliveries')->value('id');
        $job = new SendWorkshopWelcome($id);
        $job->handle($service);
        $job->handle($service);
        Mail::assertSent(WorkshopWelcome::class, 1);
        $this->assertDatabaseHas('workshop_welcome_deliveries', ['id' => $id, 'status' => 'sent']);
        $late = Ticket::factory()->create(['workshop_id' => $course->id, 'email' => 'cancelled@example.test']);
        $service->queueDue($course);
        $late->update(['status' => Ticket::STATUS_CANCELLED]);
        $id = DB::table('workshop_welcome_deliveries')->where('email', 'cancelled@example.test')->value('id');
        (new SendWorkshopWelcome($id))->handle($service);
        $this->assertDatabaseHas('workshop_welcome_deliveries', ['id' => $id, 'status' => 'cancelled']);
        $course->update(['status' => 'cancelled']);
        $this->assertSame(0, $service->queueDue($course));
        Mail::assertSent(WorkshopWelcome::class, 1);
    }

    public function test_attendance_is_per_session_and_excludes_inactive_or_other_workshop_tickets(): void
    {
        $course = $this->course();
        $ticket = $course->tickets->first();
        $inactive = Ticket::factory()->create(['status' => Ticket::STATUS_CANCELLED]);
        $session = $course->course_sessions[0]['id'];
        $this->postJson(route('admin.workshop.attendance.tickets', $course), ['session_id' => $session, 'attended_ticket_ids' => [$ticket->id, $inactive->id]])->assertOk()->assertJsonPath('attended_ticket_ids', [$ticket->id]);
        $this->assertDatabaseCount('workshop_session_attendance', 1);
        $this->postJson(route('admin.workshop.attendance.tickets', $course), ['session_id' => $course->course_sessions[1]['id'], 'attended_ticket_ids' => []])->assertOk();
        $this->assertDatabaseCount('workshop_session_attendance', 1);
        $this->postJson(route('admin.workshop.attendance.tickets', $course), ['session_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertNull($ticket->fresh()->attended_at);
    }

    public function test_course_can_be_saved_with_sessions_and_welcome_then_explicitly_resent(): void
    {
        Queue::fake();
        $course = $this->course();
        $payload = $course->only(['title', 'content', 'type', 'format', 'course_sessions', 'starts_at', 'ends_at', 'publish_at', 'closes_at', 'status', 'registration', 'hero_media_name', 'max_tickets', 'welcome_subject', 'welcome_body', 'welcome_send_at']);
        $payload['welcome_enabled'] = 1;
        $payload['course_sessions'][0]['label'] = 'Introduction to micro:bit';
        $payload['location_id'] = \App\Models\Location::factory()->create()->id;
        $this->put(route('admin.workshop.update', $course), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('', $course->fresh()->course_sessions[0]['label']);
        $this->assertSame($payload['location_id'], $course->fresh()->location_id);
        $this->assertSame('physical', $course->fresh()->type);
        $this->assertTrue($course->fresh()->isPhysicalWorkshop());
        $this->post(route('admin.workshop.welcome.send', $course), ['action' => 'send'])->assertRedirect();
        $count = DB::table('workshop_welcome_deliveries')->count();
        $this->assertGreaterThan(0, $count);
        $this->post(route('admin.workshop.welcome.send', $course), ['action' => 'send'])->assertRedirect();
        $this->assertDatabaseCount('workshop_welcome_deliveries', $count);
        $this->post(route('admin.workshop.welcome.send', $course), ['action' => 'resend'])->assertRedirect();
        $this->assertDatabaseCount('workshop_welcome_deliveries', $count * 2);
        $this->get(route('admin.workshop.welcome.preview', $course))->assertOk()->assertSee($course->fresh()->courseScheduleDisplayLines()[0])->assertDontSee('Introduction to micro:bit');
    }

    public function test_welcome_defaults_three_days_before_first_session_and_waits_until_due(): void
    {
        Queue::fake();
        $course = $this->course();
        $data = app(WorkshopCourseSettings::class)->validated(new Request([
            'format' => 'course', 'course_sessions' => $course->course_sessions, 'starts_at' => $course->starts_at,
            'ends_at' => $course->ends_at, 'welcome_enabled' => 1, 'welcome_subject' => 'Welcome', 'welcome_body' => 'Join us',
        ]), $course);
        $this->assertTrue($data['welcome_send_at']->equalTo($course->effectiveStartsAt()->subDays(3)));
        $course->update(['welcome_send_at' => now()->addHour()]);
        $this->assertSame(0, app(WorkshopWelcomeService::class)->queueDue($course));
        Queue::assertNothingPushed();
        $this->travel(61)->minutes();
        $this->assertGreaterThan(0, app(WorkshopWelcomeService::class)->queueDue($course));
    }

    public function test_allocation_uses_session_hours_and_waits_for_last_session_and_settled_tickets(): void
    {
        $course = $this->course();
        $invoice = Invoice::factory()->create(['status' => 'paid', 'total_amount' => 110, 'gst_amount' => 10, 'subtotal_amount' => 100, 'issue_date' => today(), 'created_by' => $course->user_id]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'ticket', 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110, 'details_json' => ['workshop_id' => $course->id]]);
        $course->tickets->first()->update(['invoice_id' => $invoice->id, 'invoice_line_id' => $line->id]);
        $payment = Payment::factory()->create(['total_amount' => 110, 'gst_amount' => 10, 'payment_method' => 'cash', 'received_on' => now()]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'allocated_amount' => 110]);
        $context = app(InvoiceAllocation::class)->context($invoice, null, null, $course);
        $this->assertSame(8.0, $context['assumptions']['hours']);
        $allocation = app(WorkshopAllocation::class);
        $this->assertFalse((bool) $allocation->state($course)['ready']);
        $this->travelTo($course->effectiveEndsAt()->addMinute());
        $this->assertTrue((bool) $allocation->state($course)['ready']);
        $hash = $allocation->state($course)['hash'];
        $sessions = $course->course_sessions;
        $sessions[0]['ends_at'] = Carbon::parse($sessions[0]['ends_at'])->addMinutes(15)->format('Y-m-d\TH:i');
        $course->update(['course_sessions' => $sessions]);
        $this->assertNotSame($hash, $allocation->state($course)['hash']);
        Ticket::factory()->create(['workshop_id' => $course->id, 'status' => Ticket::STATUS_PENDING_XFER]);
        $course->unsetRelation('tickets');
        $this->assertFalse((bool) $allocation->state($course)['ready']);
    }

    public function test_completed_booking_queues_the_due_welcome_alongside_confirmation(): void
    {
        Queue::fake();
        $course = $this->course();
        $delivery = WorkshopTicketEmail::create([
            'workshop_id' => $course->id, 'ticket_ids' => $course->tickets->pluck('id')->all(),
            'recipient_email' => 'parent@example.test', 'recipient_name' => 'Parent', 'payment_method' => 'free',
            'amount' => 0, 'status' => 'pending',
        ]);
        $this->assertTrue(app(WorkshopTicketOrderEmailService::class)->queueCombinedEmail($delivery));
        Queue::assertPushed(SendWorkshopWelcome::class);
        Queue::assertPushed(SendEmail::class, function ($job): bool {
            return $job->mailable instanceof TicketOrderConfirmation
                && count($job->mailable->workshop['schedule']) === 8;
        });
    }

    public function test_course_creation_and_attendance_export_preserve_the_schedule(): void
    {
        $course = $this->course();
        $payload = $course->only(['title', 'content', 'type', 'format', 'course_sessions', 'starts_at', 'ends_at', 'publish_at', 'closes_at', 'status', 'registration', 'hero_media_name', 'max_tickets']);
        $payload['title'] = 'New eight week course';
        $this->post(route('admin.workshop.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $created = Workshop::where('title', 'New eight week course')->firstOrFail();
        $this->assertSame(8.0, $created->teachingHours());
        $this->assertFalse($created->welcome_enabled);
        $ticket = $course->tickets->first();
        $session = $course->course_sessions[0]['id'];
        $this->postJson(route('admin.workshop.attendance.tickets', $course), ['session_id' => $session, 'attended_ticket_ids' => [$ticket->id]])->assertOk();
        $csv = $this->get(route('admin.workshop.attendance.csv', [$course, 'session_id' => $session]))->assertOk()->streamedContent();
        $this->assertStringContainsString(\Illuminate\Support\Carbon::parse($course->course_sessions[0]['starts_at'])->format('j M Y g:ia'), $csv);
        $this->assertStringContainsString('Attended', $csv);
        $other = $this->get(route('admin.workshop.attendance.csv', [$course, 'session_id' => $course->course_sessions[1]['id']]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Not marked', $other);
    }

    public function test_course_delivery_is_determined_by_its_location(): void
    {
        $course = $this->course();
        $online = \App\Models\Location::factory()->create(['name' => 'Online']);
        $payload = $course->only(['title', 'content', 'format', 'course_sessions', 'starts_at', 'ends_at', 'publish_at', 'closes_at', 'status', 'registration', 'hero_media_name', 'max_tickets']);
        $payload['type'] = 'physical';
        $payload['location_id'] = $online->id;
        $this->put(route('admin.workshop.update', $course), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $course->refresh();
        $this->assertSame('online', $course->type);
        $this->assertSame($online->id, $course->location_id);
        $this->assertTrue($course->isOnlineWorkshop());
        $course->update(['location_id' => null]);
        $this->assertTrue($course->fresh()->isOnlineWorkshop());
        $this->get(route('admin.workshop.edit', $course))->assertOk()->assertDontSee('id="course-delivery"', false)->assertDontSee('One ticket covers every session.');
    }
}
