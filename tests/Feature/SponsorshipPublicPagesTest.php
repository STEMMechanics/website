<?php

namespace Tests\Feature;

use App\Models\ManualSponsorSupport;
use App\Models\Invoice;
use App\Models\Sponsor;
use App\Models\SponsorshipInvoiceRequest;
use App\Models\SponsorshipProject;
use App\Models\SponsorshipRecognitionLevel;
use App\Models\Sponsorship;
use App\Models\User;
use App\Services\SponsorshipService;
use Database\Seeders\SponsorshipProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SponsorshipPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SponsorshipProjectSeeder::class);
    }

    public function test_sponsorship_landing_page_uses_business_and_community_partnership_language(): void
    {
        $response = $this->get(route('sponsor.index'));

        $response->assertOk()
            ->assertSee('STEMMechanics is an independently operated Australian small business.')
            ->assertSee('Sponsorship directly supports the delivery and expansion of our STEM programs.')
            ->assertSee('Choose a sponsorship option that works for you.')
            ->assertSee('Free & subsidised workshops')
            ->assertSee('Regional communities')
            ->assertSee('Equipment & workshop resources')
            ->assertSee('Online learning & STEMCraft')
            ->assertSee('Community Support')
            ->assertSee('Is STEMMechanics a charity?')
            ->assertDontSee('Make a contribution that works for you.')
            ->assertDontSee('Open-source tools')
            ->assertDontSee('Buy us a Coffee');
    }

    public function test_sponsors_page_is_available_with_partnership_call_to_action(): void
    {
        $response = $this->get(route('sponsors.index'));

        $response->assertOk()
            ->assertSee('Interested in partnering with STEMMechanics?')
            ->assertSee('Explore sponsorship opportunities for businesses, organisations and individuals.');
    }

    public function test_sponsors_page_shows_a_public_support_message_when_the_record_has_one(): void
    {
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $level = SponsorshipRecognitionLevel::query()->where('name', 'Sponsors')->firstOrFail();
        $sponsor = Sponsor::query()->create([
            'contact_name' => 'ABC Engineering contact',
            'sponsor_type' => 'organisation',
            'company_name' => 'ABC Engineering',
            'country' => 'Australia',
            'recognition_public' => true,
            'recognition_approved_at' => now(),
        ]);
        $sponsor->forceFill(['public_message' => 'Supporting regional STEM workshops in North Queensland.'])->save();
        ManualSponsorSupport::query()->create([
            'sponsor_id' => $sponsor->id,
            'project_id' => $project->id,
            'recognition_level_id' => $level->id,
            'support_method' => 'other_benefit',
            'value_amount' => 500,
            'currency' => 'AUD',
            'starts_on' => today(),
        ]);

        $response = $this->get(route('sponsors.index'));

        $response->assertOk()
            ->assertSee('ABC Engineering')
            ->assertSee('Supporting regional STEM workshops in North Queensland.');
    }

    public function test_community_support_page_remains_available(): void
    {
        $response = $this->get(route('sponsor.community-support'));

        $response->assertOk()
            ->assertViewIs('sponsorship.community-support')
            ->assertSee('Community Support')
            ->assertSee('one-time or monthly sponsorship')
            ->assertDontSee('Buy us a Coffee');
    }

    public function test_legacy_community_support_url_redirects_to_the_renamed_page(): void
    {
        $this->get('/sponsor/coffee?ref=community')
            ->assertRedirect(route('sponsor.community-support', ['ref' => 'community']));
    }

    public function test_confirmed_invoice_request_redirects_to_a_success_page(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-SPONSOR-TEST',
            'billing_email' => 'partner@example.test',
        ]);
        $token = Str::random(64);
        SponsorshipInvoiceRequest::query()->create([
            'email' => 'partner@example.test',
            'token_hash' => hash('sha256', $token),
            'payload' => ['frequency' => 'one_time'],
            'expires_at' => now()->addMinutes(10),
            'used_at' => now(),
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->post(route('sponsor.invoice-request.confirm.submit', ['token' => $token]));

        $response->assertRedirect(route('sponsor.invoice-request.complete', ['token' => $token]));
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertViewIs('sponsorship.invoice-request-complete')
            ->assertSee('Your sponsorship invoice has been created')
            ->assertDontSee('already been confirmed');
    }

    public function test_guest_sponsorship_invoice_is_linked_to_a_ghost_user(): void
    {
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $sponsor = app(SponsorshipService::class)->createSponsor([
            'email' => 'business@example.test',
            'contact_name' => 'Business Contact',
            'company_name' => 'Example Business',
            'sponsor_type' => 'organisation',
            'country' => 'Australia',
            'non_resident_declaration' => false,
        ]);
        $sponsorship = Sponsorship::query()->create([
            'sponsor_id' => $sponsor->id,
            'project_id' => $project->id,
            'checkout_type' => Sponsorship::CHECKOUT_TYPE_BUSINESS,
            'frequency' => 'monthly',
            'billing_method' => 'invoice',
            'amount' => 500,
            'currency' => 'AUD',
            'status' => Sponsorship::STATUS_PENDING,
            'started_at' => now(),
        ]);

        $payment = app(SponsorshipService::class)->createUnpaidInvoice($sponsorship);

        $this->assertNotNull($sponsor->fresh()->user_id);
        $this->assertNull($sponsor->fresh()->user->email_verified_at);
        $this->assertSame((string) $sponsor->fresh()->user_id, (string) $payment->invoice->user_id);

        $invoice = $payment->invoice->fresh(['lines']);
        $this->assertSame(
            (float) $invoice->subtotal_amount,
            round($invoice->lines->sum(fn ($line): float => (float) $line->line_total_ex_tax), 2),
        );
        $this->assertSame(
            (float) $invoice->gst_amount,
            round($invoice->lines->sum(fn ($line): float => (float) $line->tax_amount), 2),
        );
        $this->assertSame(
            (float) $invoice->total_amount,
            round($invoice->lines->sum(fn ($line): float => (float) $line->line_total_inc_tax), 2),
        );
    }

    public function test_logged_in_checkout_with_a_different_billing_email_uses_that_email_for_the_invoice_account(): void
    {
        Queue::fake();
        $account = User::factory()->create(['email' => 'logged-in@example.test']);
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $option = $project->options()
            ->where('checkout_group', 'business')
            ->where('frequency', 'one_time')
            ->where('enabled', true)
            ->orderBy('amount')
            ->firstOrFail();
        $token = Str::random(64);
        SponsorshipInvoiceRequest::query()->create([
            'email' => 'billing-contact@example.test',
            'token_hash' => hash('sha256', $token),
            'payload' => [
                'sponsor_type' => 'organisation',
                'details' => [
                    'email' => 'billing-contact@example.test',
                    'contact_name' => 'Billing Contact',
                    'company_name' => 'Example Organisation',
                    'country' => 'Australia',
                    'non_resident_declaration' => false,
                ],
                'project_id' => $project->id,
                'option_id' => $option->id,
                'frequency' => 'one_time',
                'amount' => (float) $option->amount,
                'currency' => 'AUD',
                'recognition_enabled' => false,
            ],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($account)
            ->post(route('sponsor.invoice-request.confirm.submit', ['token' => $token]));

        $response->assertRedirect(route('sponsor.invoice-request.complete', ['token' => $token]));
        $invoice = Invoice::query()->where('billing_email', 'billing-contact@example.test')->latest('id')->firstOrFail();

        $this->assertNotSame((string) $account->id, (string) $invoice->user_id);
        $this->assertSame('billing-contact@example.test', $invoice->user?->email);
        $this->assertNull($invoice->user?->email_verified_at);
    }

    public function test_business_sponsorship_selection_page_remains_renderable(): void
    {
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $option = $project->options()
            ->where('checkout_group', 'business')
            ->where('frequency', 'one_time')
            ->where('enabled', true)
            ->orderBy('amount')
            ->firstOrFail();

        $response = $this->withSession([
            'sponsorship.checkout' => [
                'checkout_type' => 'business',
                'sponsor_type' => 'organisation',
                'details' => [
                    'email' => 'partner@example.test',
                    'contact_name' => 'Partner Contact',
                    'company_name' => 'Partner Organisation',
                    'country' => 'Australia',
                ],
                'payment' => [
                    'option_id' => $option->id,
                    'frequency' => 'one_time',
                    'payment_method' => 'square',
                ],
            ],
        ])->get(route('sponsor.payment'));

        $response->assertOk()
            ->assertSee('Choose your sponsorship')
            ->assertSee($option->label)
            ->assertSee('Continue to payment');
    }

    public function test_international_business_checkout_shows_card_form_without_a_payment_selector(): void
    {
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $option = $project->options()
            ->where('checkout_group', 'business')
            ->where('frequency', 'one_time')
            ->where('enabled', true)
            ->orderBy('amount')
            ->firstOrFail();

        $response = $this->withSession([
            'sponsorship.checkout' => [
                'checkout_type' => 'business',
                'sponsor_type' => 'organisation',
                'details' => [
                    'email' => 'partner@example.test',
                    'contact_name' => 'Partner Contact',
                    'company_name' => 'Partner Organisation',
                    'country' => 'United States',
                ],
                'payment' => [
                    'option_id' => $option->id,
                    'frequency' => 'one_time',
                    'payment_method' => 'square',
                ],
            ],
        ])->get(route('sponsor.checkout'));

        $response->assertOk()
            ->assertSee('Card payment')
            ->assertDontSee('Pay by card')
            ->assertDontSee('Invoice payment is currently available for Australian sponsors.')
            ->assertDontSee('Pay by invoice');
    }

    public function test_international_business_checkout_cannot_request_an_invoice(): void
    {
        $project = SponsorshipProject::query()->where('is_primary', true)->firstOrFail();
        $option = $project->options()
            ->where('checkout_group', 'business')
            ->where('frequency', 'one_time')
            ->where('enabled', true)
            ->orderBy('amount')
            ->firstOrFail();

        $response = $this->from(route('sponsor.checkout'))
            ->withSession([
                'sponsorship.checkout' => [
                    'checkout_type' => 'business',
                    'sponsor_type' => 'organisation',
                    'details' => [
                        'email' => 'partner@example.test',
                        'contact_name' => 'Partner Contact',
                        'company_name' => 'Partner Organisation',
                        'country' => 'United States',
                    ],
                    'payment' => [
                        'option_id' => $option->id,
                        'frequency' => 'one_time',
                        'payment_method' => 'square',
                    ],
                ],
            ])
            ->post(route('sponsor.process'), ['payment_method' => 'invoice']);

        $response->assertRedirect(route('sponsor.checkout'))
            ->assertSessionHasErrors('payment_method');
        $this->assertSame(0, SponsorshipInvoiceRequest::query()->count());
    }

    public function test_logged_out_visitor_can_start_a_new_business_sponsorship(): void
    {
        $this->assertGuest();

        $start = $this->get(route('sponsor.start', ['new' => 1]));

        $start->assertRedirect(route('sponsor.details'));
        $this->assertSame('business', session('sponsorship.checkout.checkout_type'));

        $details = $this->get($start->headers->get('Location'));

        $details->assertOk()
            ->assertViewIs('sponsorship.details')
            ->assertSee('Business and invoice details');
        $this->assertGuest();
    }
}
