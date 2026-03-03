<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ContactMessage;
use App\Models\User;
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

        $response = $this->post(route('contact.send'), [
            'name' => 'Alex Harper',
            'email' => 'alex@example.com',
            'subject' => 'Workshop enquiry',
            'message' => 'We would like to book a robotics workshop for Year 6 students.',
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
}
