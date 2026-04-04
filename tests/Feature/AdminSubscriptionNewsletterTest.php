<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Mail\UserWelcome;
use App\Models\EmailSubscriptions;
use App\Models\SentEmail;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_admin_can_queue_test_newsletter_for_arbitrary_email(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-test-now'), [
                'test_email' => 'SpamTester@Example.com',
            ]);

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHas('message-title', 'Newsletter queued');
        $this->assertDatabaseCount('email_subscriptions', 0);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $this->assertSame('spamtester@example.com', (string) $job->to);
            $this->assertInstanceOf(UpcomingWorkshops::class, $job->mailable);

            return true;
        });
    }

    public function test_upcoming_workshops_mailable_includes_list_unsubscribe_header_when_link_is_present(): void
    {
        $mailable = new UpcomingWorkshops('subscriber@example.com');
        $mailable->withUnsubscribeLink('https://www.stemmechanics.com.au/unsubscribe/test-token');

        $headers = $mailable->unsubscribeHeaders();

        $this->assertSame(
            '<https://www.stemmechanics.com.au/unsubscribe/test-token>',
            $headers['List-Unsubscribe'] ?? null
        );
        $this->assertSame(
            'List-Unsubscribe=One-Click',
            $headers['List-Unsubscribe-Post'] ?? null
        );
    }

    public function test_send_email_job_adds_unsubscribe_headers_to_final_subscription_message(): void
    {
        config(['mail.default' => 'array']);

        $job = new SendEmail('subscriber@example.com', new UserWelcome('subscriber@example.com'));
        $job->handle();

        $transport = app('mail.manager')->mailer()->getSymfonyTransport();
        $sentMessage = $transport->messages()->last();
        $headers = $sentMessage->getOriginalMessage()->getHeaders();

        $this->assertSame(
            '<'.route('unsubscribe', ['email' => $job->sentEmailId]).'>',
            $headers->get('List-Unsubscribe')?->getBodyAsString()
        );
        $this->assertSame(
            'List-Unsubscribe=One-Click',
            $headers->get('List-Unsubscribe-Post')?->getBodyAsString()
        );
    }

    public function test_subscription_unsubscribe_endpoint_accepts_one_click_post_requests(): void
    {
        $subscription = EmailSubscriptions::query()->create([
            'email' => 'subscriber@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);
        $sentEmail = SentEmail::query()->create([
            'recipient' => 'subscriber@example.com',
            'mailable_class' => UserWelcome::class,
            'status' => SentEmail::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $response = $this->post(route('unsubscribe', ['email' => $sentEmail->id]));

        $response->assertOk();
        $response->assertSeeText('Unsubscribed.');
        $this->assertDatabaseMissing('email_subscriptions', [
            'id' => $subscription->id,
        ]);
    }

    public function test_admin_test_newsletter_requires_valid_email(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->from(route('admin.subscription.index'))
            ->post(route('admin.subscription.send-test-now'), [
                'test_email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('admin.subscription.index'));
        $response->assertSessionHasErrors('test_email');
        Queue::assertNothingPushed();
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

    public function test_index_renders_distinct_latest_newsletter_statuses_per_subscription_row(): void
    {
        $admin = $this->createAdminUser();
        $queuedSubscription = EmailSubscriptions::query()->create([
            'email' => 'queued@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);
        $sentSubscription = EmailSubscriptions::query()->create([
            'email' => 'sent@example.com',
            'confirmed' => now()->toDateTimeString(),
        ]);

        SentEmail::query()->create([
            'recipient' => $queuedSubscription->email,
            'mailable_class' => UpcomingWorkshops::class,
            'status' => SentEmail::STATUS_QUEUED,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $sent = SentEmail::query()->create([
            'recipient' => $sentSubscription->email,
            'mailable_class' => UpcomingWorkshops::class,
            'status' => SentEmail::STATUS_SENT,
            'sent_at' => now()->subMinute(),
        ]);
        $sent->forceFill([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->saveQuietly();

        $response = $this->actingAs($admin)->get(route('admin.subscription.index'));

        $response->assertOk();
        $response->assertSeeText('queued@example.com');
        $response->assertSeeText('sent@example.com');
        $response->assertSeeText('Queued');
        $response->assertSeeText('Sent');
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
            if (! $map instanceof Collection) {
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
