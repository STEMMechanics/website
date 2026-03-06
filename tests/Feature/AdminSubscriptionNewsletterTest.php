<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\EmailSubscriptions;
use App\Models\SentEmail;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminSubscriptionNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_queue_newsletter_for_confirmed_subscription(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $subscription = EmailSubscriptions::query()->create([
            'email' => 'subscriber@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-now', $subscription));

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHas('message-title', 'Newsletter queued');

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $this->assertSame('subscriber@example.com', (string) $job->to);
            $this->assertInstanceOf(UpcomingWorkshops::class, $job->mailable);

            return true;
        });
    }

    public function test_admin_cannot_queue_newsletter_for_unconfirmed_subscription(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $subscription = EmailSubscriptions::query()->create([
            'email' => 'subscriber@example.com',
            'confirmed' => null,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-now', $subscription));

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHas('message-title', 'Newsletter not sent');
        Queue::assertNothingPushed();
    }

    public function test_admin_can_queue_newsletter_for_all_confirmed_subscriptions(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();

        EmailSubscriptions::query()->create([
            'email' => 'alpha@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);
        EmailSubscriptions::query()->create([
            'email' => 'beta@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);
        EmailSubscriptions::query()->create([
            'email' => 'alpha@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);
        EmailSubscriptions::query()->create([
            'email' => 'invalid-email',
            'confirmed' => now()->toDateTimeString(),
        ]);
        EmailSubscriptions::query()->create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => null,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-all-now'));

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHas('message-title', 'Newsletter queued');

        Queue::assertPushed(SendEmail::class, 2);
        Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => (string) $job->to === 'alpha@example.com');
        Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => (string) $job->to === 'beta@example.com');
    }

    public function test_admin_send_all_now_warns_when_no_confirmed_valid_subscriptions_exist(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        EmailSubscriptions::query()->create([
            'email' => 'invalid-email',
            'confirmed' => now()->toDateTimeString(),
        ]);
        EmailSubscriptions::query()->create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => null,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-all-now'));

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHas('message-title', 'Newsletter not sent');
        Queue::assertNothingPushed();
    }

    public function test_index_builds_latest_newsletter_status_per_subscription_email(): void
    {
        $admin = $this->createAdminUser();
        $subscription = EmailSubscriptions::query()->create([
            'email' => 'subscriber@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);

        $older = SentEmail::query()->create([
            'recipient' => $subscription->email,
            'mailable_class' => UpcomingWorkshops::class,
            'status' => SentEmail::STATUS_SENT,
            'sent_at' => now()->subHour(),
        ]);
        $older->forceFill([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ])->saveQuietly();

        $latest = SentEmail::query()->create([
            'recipient' => $subscription->email,
            'mailable_class' => UpcomingWorkshops::class,
            'status' => SentEmail::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => 'Template parse error',
        ]);
        $latest->forceFill([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('admin.subscription.index'));

        $response->assertOk();
        $response->assertViewHas('latestNewsletterByEmail', function ($map) use ($latest): bool {
            if (! $map instanceof \Illuminate\Support\Collection) {
                return false;
            }

            $record = $map->get('subscriber@example.com');

            return $record instanceof SentEmail
                && (string) $record->id === (string) $latest->id
                && (string) $record->status === SentEmail::STATUS_FAILED;
        });
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
