<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\WorkshopTicketBroadcast;
use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class AdminWorkshopTicketEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_bulk_ticket_email_dedupes_recipients_and_queues_single_job(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $holder = User::factory()->create(['email' => 'parent@example.com']);
        $linkedUser = User::factory()->create(['email' => 'guardian@example.com']);
        $workshop = $this->createTicketWorkshop();

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holder->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'parent@example.com',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => null,
            'status' => Ticket::STATUS_PENDING_DOOR,
            'email' => 'other@example.com',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $linkedUser->id,
            'status' => Ticket::STATUS_CANCELLED,
            'email' => '',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => null,
            'status' => Ticket::STATUS_HOLD,
            'email' => 'hold@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => "Line 1\nLine 2",
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message', 'Email sent to 3 recipients.');

        Queue::assertPushed(SendEmail::class, 1);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            $this->assertSame((string) $admin->email, (string) $job->to);
            $this->assertInstanceOf(WorkshopTicketBroadcast::class, $job->mailable);

            $recipients = $this->extractPrivateArrayProperty($job->mailable, 'bccRecipients');
            sort($recipients);
            $this->assertSame([
                'guardian@example.com',
                'other@example.com',
                'parent@example.com',
            ], $recipients);

            return true;
        });
    }

    public function test_bulk_ticket_email_warns_when_no_recipients_found(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => 'Hello ticket holders',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message-title', 'No recipients');
        Queue::assertNothingPushed();
    }

    public function test_bulk_ticket_email_falls_back_to_mail_from_when_admin_email_invalid(): void
    {
        Queue::fake();
        config(['mail.from.address' => 'fallback@example.com']);

        $admin = $this->createAdminUser();
        $admin->forceFill(['email' => ''])->saveQuietly();

        $workshop = $this->createTicketWorkshop();
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'recipient@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => 'Hello',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        Queue::assertPushed(SendEmail::class, fn (SendEmail $job) => $job->to === 'fallback@example.com');
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

    private function createTicketWorkshop(): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Workshop Tickets',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
            'status' => 'open',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function extractPrivateArrayProperty(object $object, string $property): array
    {
        $reflection = new ReflectionClass($object);
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        $value = $propertyReflection->getValue($object);

        return is_array($value) ? array_values($value) : [];
    }
}
