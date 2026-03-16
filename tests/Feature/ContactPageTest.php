<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ContactMessage;
use App\Models\User;
use App\Support\FormGuard;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        config()->set('security.altcha_enabled', false);
        config()->set('security.form_protection.minimum_seconds', 2);
        config()->set('security.form_protection.rate_limit_per_minute', 5);
        config()->set('mail.contact_to.address', 'hello@stemmechanics.com.au');
    }

    public function test_contact_page_renders_form(): void
    {
        $response = $this->get(route('contact'));

        $response->assertOk();
        $response->assertSee('Tell us what you need');
        $response->assertSee('Send message');
    }

    public function test_contact_submission_queues_message_and_redirects(): void
    {
        Queue::fake();
        $guardToken = $this->contactFormGuardToken();

        $this->travel(3)->seconds();

        $response = $this->post(route('contact.send'), [
            'name' => 'Alex Harper',
            'email' => 'alex@example.com',
            'subject' => 'Workshop enquiry',
            'message' => 'We would like to book a robotics workshop for Year 6 students.',
            FormGuard::TOKEN_FIELD => $guardToken,
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) {
            if ($job->to !== 'hello@stemmechanics.com.au') {
                return false;
            }

            $this->assertInstanceOf(ContactMessage::class, $job->mailable);
            $this->assertSame('Alex Harper', $job->mailable->senderName);
            $this->assertSame('alex@example.com', $job->mailable->senderEmail);
            $this->assertSame('Workshop enquiry', $job->mailable->subjectLine);
            $job->mailable->build();
            $this->assertSame('alex@example.com', $job->mailable->replyTo[0]['address'] ?? null);
            $this->assertSame('Alex Harper', $job->mailable->replyTo[0]['name'] ?? null);

            return true;
        });
    }

    public function test_contact_page_prefills_signed_in_user_details(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Cole',
            'email' => 'jamie@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('contact'));

        $response->assertOk();
        $response->assertSee('Jamie Cole');
        $response->assertSee('jamie@example.com');
    }

    public function test_contact_submission_rejects_honeypot_hits(): void
    {
        Queue::fake();
        $guardToken = $this->contactFormGuardToken();

        $this->travel(3)->seconds();

        $response = $this->post(route('contact.send'), [
            'name' => 'Alex Harper',
            'email' => 'alex@example.com',
            'subject' => 'Workshop enquiry',
            'message' => 'We would like to book a robotics workshop for Year 6 students.',
            FormGuard::TOKEN_FIELD => $guardToken,
            app(FormGuard::class)->honeypotField('contact') => 'https://spam.invalid',
        ]);

        $response->assertSessionHasErrors(FormGuard::ERROR_KEY);
        Queue::assertNothingPushed();
    }

    public function test_contact_submission_rejects_submissions_that_are_too_fast(): void
    {
        Queue::fake();
        $guardToken = $this->contactFormGuardToken();

        $response = $this->post(route('contact.send'), [
            'name' => 'Alex Harper',
            'email' => 'alex@example.com',
            'subject' => 'Workshop enquiry',
            'message' => 'We would like to book a robotics workshop for Year 6 students.',
            FormGuard::TOKEN_FIELD => $guardToken,
        ]);

        $response->assertSessionHasErrors(FormGuard::ERROR_KEY);
        Queue::assertNothingPushed();
    }

    public function test_contact_submission_is_rate_limited_by_ip(): void
    {
        Queue::fake();
        config()->set('security.form_protection.rate_limit_per_minute', 2);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $guardToken = $this->contactFormGuardToken();
            $this->travel(3)->seconds();

            $response = $this->post(route('contact.send'), [
                'name' => 'Alex Harper',
                'email' => 'alex@example.com',
                'subject' => 'Workshop enquiry',
                'message' => 'We would like to book a robotics workshop for Year 6 students.',
                FormGuard::TOKEN_FIELD => $guardToken,
            ]);

            $response->assertRedirect(route('contact'));
        }

        $guardToken = $this->contactFormGuardToken();
        $this->travel(3)->seconds();

        $response = $this->post(route('contact.send'), [
            'name' => 'Alex Harper',
            'email' => 'alex@example.com',
            'subject' => 'Workshop enquiry',
            'message' => 'We would like to book a robotics workshop for Year 6 students.',
            FormGuard::TOKEN_FIELD => $guardToken,
        ]);

        $response->assertStatus(429);
        Queue::assertPushed(SendEmail::class, 2);
    }

    private function contactFormGuardToken(): string
    {
        $response = $this->get(route('contact'));
        $html = (string) $response->getContent();

        preg_match('/name="_form_guard" value="([^"]+)"/', $html, $matches);

        $this->assertArrayHasKey(1, $matches);

        return html_entity_decode($matches[1], ENT_QUOTES);
    }
}
