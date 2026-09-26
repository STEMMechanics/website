<?php

namespace Tests\Feature;

use App\Jobs\SendWorkshopInterestReminder;
use App\Mail\WorkshopInterestReminder;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopInterest;
use App\Services\WorkshopInterestReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkshopInterestReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_day_and_two_hour_reminders_are_queued_at_their_scheduled_times_only_once(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00', config('app.timezone')));
        $startsAt = now()->copy()->addDays(2);
        $workshop = $this->createInterestWorkshop($startsAt);
        $interest = $this->createInterest($workshop);
        Queue::fake();

        $service = app(WorkshopInterestReminderService::class);

        $this->assertSame(1, $service->queueDue());
        Queue::assertPushed(SendWorkshopInterestReminder::class, fn (SendWorkshopInterestReminder $job): bool => $job->interestId === (int) $interest->id && $job->type === 'two_days');
        $this->assertSame(now()->toDateTimeString(), $interest->fresh()->two_day_reminder_queued_at->toDateTimeString());
        $this->assertNull($interest->fresh()->two_hour_reminder_queued_at);
        $this->assertSame(0, $service->queueDue());

        $this->travelTo($startsAt->copy()->subHours(2));

        $this->assertSame(1, $service->queueDue());
        Queue::assertPushed(SendWorkshopInterestReminder::class, 2);
        Queue::assertPushed(SendWorkshopInterestReminder::class, fn (SendWorkshopInterestReminder $job): bool => $job->interestId === (int) $interest->id && $job->type === 'two_hours');
        $this->assertSame(now()->toDateTimeString(), $interest->fresh()->two_hour_reminder_queued_at->toDateTimeString());
        $this->assertSame(0, $service->queueDue());
    }

    public function test_interest_reminder_is_sent_to_the_registered_email_and_recorded_once(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00', config('app.timezone')));
        $workshop = $this->createInterestWorkshop(now()->copy()->addHours(2));
        $interest = $this->createInterest($workshop, [
            'name' => 'Jamie Maker',
            'email' => 'jamie@example.com',
            'two_hour_reminder_queued_at' => now(),
        ]);
        Mail::fake();

        $job = new SendWorkshopInterestReminder((int) $interest->id, 'two_hours');
        $job->handle();
        $job->handle();

        Mail::assertSent(WorkshopInterestReminder::class, function (WorkshopInterestReminder $mail) use ($workshop, $interest): bool {
            return $mail->hasTo('jamie@example.com')
                && $mail->workshop->is($workshop)
                && $mail->interest->is($interest)
                && $mail->type === 'two_hours';
        });
        Mail::assertSent(WorkshopInterestReminder::class, 1);
        $this->assertNotNull($interest->fresh()->two_hour_reminder_sent_at);

        $rendered = (new WorkshopInterestReminder($workshop, $interest, 'two_hours'))->render();
        $this->assertStringContainsString('Hi Jamie,', $rendered);
        $this->assertStringContainsString('in about two hours', $rendered);
        $this->assertStringContainsString($workshop->getLocationName(), $rendered);
        $this->assertStringContainsString(route('workshop.show', $workshop), $rendered);
        $this->assertMatchesRegularExpression('/<strong[^>]*>When:<\/strong>.*?<br\s*\/?>(?:\s*)<strong[^>]*>Where:<\/strong>/s', $rendered);
        $this->assertStringContainsString('/workshop-interest-reminders/'.$interest->id.'/unsubscribe?signature=', $rendered);

        $sampleMailable = new WorkshopInterestReminder($workshop, $interest, 'two_days', true);
        $sampleMailable->assertHasSubject('[Sample] Workshop interest reminder: '.$workshop->title);
        $sample = $sampleMailable->render();
        $this->assertStringContainsString('This is a sample of the two-day workshop-interest reminder.', $sample);
        $this->assertStringNotContainsString('it starts in two days', $sample);
    }

    public function test_recent_interest_does_not_receive_a_late_two_day_email_with_misleading_timing(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00', config('app.timezone')));
        $workshop = $this->createInterestWorkshop(now()->copy()->addDay());
        $this->createInterest($workshop);
        Queue::fake();

        $this->assertSame(0, app(WorkshopInterestReminderService::class)->queueDue());
        Queue::assertNothingPushed();
    }

    public function test_queued_interest_reminder_is_not_sent_if_the_workshop_is_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00', config('app.timezone')));
        $workshop = $this->createInterestWorkshop(now()->copy()->addHours(2));
        $interest = $this->createInterest($workshop, ['two_hour_reminder_queued_at' => now()]);
        $workshop->update(['status' => 'cancelled']);
        Mail::fake();

        (new SendWorkshopInterestReminder((int) $interest->id, 'two_hours'))->handle();

        Mail::assertNothingSent();
        $this->assertNull($interest->fresh()->two_hour_reminder_queued_at);
        $this->assertNull($interest->fresh()->two_hour_reminder_sent_at);
    }

    public function test_signed_reminder_unsubscribe_page_requires_confirmation_and_stops_future_reminders(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 10:00:00', config('app.timezone')));
        $workshop = $this->createInterestWorkshop(now()->copy()->addDays(2));
        $interest = $this->createInterest($workshop, [
            'two_day_reminder_queued_at' => now(),
            'two_hour_reminder_queued_at' => now(),
        ]);
        $signedUrl = URL::signedRoute('workshop-interest-reminders.unsubscribe', ['interest' => $interest]);

        $this->get($signedUrl)
            ->assertOk()
            ->assertSee('Stop workshop reminders?')
            ->assertSee('Your interest in the workshop will remain recorded.');
        $this->assertNull($interest->fresh()->reminders_unsubscribed_at);

        $this->post($signedUrl)
            ->assertOk()
            ->assertSee('Reminders stopped');

        $interest->refresh();
        $this->assertNotNull($interest->reminders_unsubscribed_at);
        $this->assertNull($interest->two_day_reminder_queued_at);
        $this->assertNull($interest->two_hour_reminder_queued_at);

        Queue::fake();
        $this->assertSame(0, app(WorkshopInterestReminderService::class)->queueDue());
        Queue::assertNothingPushed();
    }

    public function test_unsigned_reminder_unsubscribe_link_is_rejected(): void
    {
        $workshop = $this->createInterestWorkshop(now()->copy()->addDays(2));
        $interest = $this->createInterest($workshop);

        $this->get(route('workshop-interest-reminders.unsubscribe', ['interest' => $interest]))
            ->assertForbidden();

        $this->assertNull($interest->fresh()->reminders_unsubscribed_at);
    }

    private function createInterestWorkshop(Carbon $startsAt): Workshop
    {
        $author = User::factory()->create();
        $location = Location::factory()->create(['name' => 'Maker Lab']);
        $hero = Media::factory()->create([
            'name' => 'interest-reminder-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $author->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Robotics Interest Workshop',
            'content' => '<p>Robots.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHour(),
            'status' => 'open',
            'type' => Workshop::TYPE_PHYSICAL,
            'registration' => 'interest',
            'location_id' => (string) $location->id,
            'user_id' => (string) $author->id,
            'hero_media_name' => (string) $hero->name,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function createInterest(Workshop $workshop, array $overrides = []): WorkshopInterest
    {
        $user = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Maker',
            'email' => 'jamie@example.com',
        ]);

        return WorkshopInterest::query()->create(array_merge([
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'name' => 'Jamie Maker',
            'email' => 'jamie@example.com',
            'phone' => '',
        ], $overrides));
    }
}
