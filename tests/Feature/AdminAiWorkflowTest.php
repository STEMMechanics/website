<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\PickListTemplate;
use App\Models\Product;
use App\Models\SiteOption;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['services.openai.api_key' => 'test-api-key']);
    }

    public function test_ai_hub_is_removed_while_embedded_ai_workflows_remain_available(): void
    {
        $this->assertFalse(Route::has('admin.ai.index'));
        $this->assertTrue(Route::has('admin.ai.expenses.extract'));
        $this->assertFalse(Route::has('admin.ai.workshops.prep'));
        $this->assertTrue(Route::has('admin.ai.workshops.social-copy'));
        $this->assertTrue(Route::has('admin.ai.newsletter.header'));
        $this->assertTrue(Route::has('admin.ai.products.draft'));
    }

    public function test_admin_can_extract_expense_fields_from_a_pdf_with_schema_and_evidence(): void
    {
        Supplier::query()->create([
            'name' => 'Jaycar Pty Ltd',
            'supplier' => 'jaycar pty ltd',
            'mode' => 'default',
            'splits' => [],
        ]);

        $fields = [
            'document_type' => 'Tax invoice',
            'supplier' => 'Jaycar Pty Ltd',
            'description' => 'Elegoo PLA filament White 1.75mm 1kg',
            'invoice_id' => '1344827',
            'paid_on' => '2026-09-18',
            'date_basis' => 'invoice_date',
            'total_amount' => '39.90',
            'gst_amount' => '3.63',
            'currency' => 'AUD',
            'evidence' => [
                'supplier' => ['page' => 1, 'text' => 'Jaycar Pty Ltd'],
                'description' => ['page' => 2, 'text' => 'Elegoo PLA filament White 1.75mm 1kg'],
                'invoice_id' => ['page' => 1, 'text' => 'Invoice No. 1344827'],
                'paid_on' => ['page' => 1, 'text' => 'Date: 18 Sep 2026'],
                'total_amount' => ['page' => 2, 'text' => 'Total $39.90'],
                'gst_amount' => ['page' => 2, 'text' => 'Includes GST of $3.63'],
            ],
            'needs_review' => [],
        ];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode($fields)], 200)]);

        $response = $this->actingAs($this->createAdminUser())->postJson(route('admin.ai.expenses.extract'), [
            'receipt_pdf' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('result.document_type', 'Tax invoice')
            ->assertJsonPath('result.supplier', 'Jaycar Pty Ltd')
            ->assertJsonPath('result.invoice_id', '1344827')
            ->assertJsonPath('result.evidence.invoice_id.text', 'Invoice No. 1344827')
            ->assertJsonPath('result.supplier_match', 'Existing supplier matched: Jaycar Pty Ltd')
            ->assertJsonPath('result.needs_review', []);

        Http::assertSent(function (ClientRequest $request): bool {
            $data = $request->data();
            $fileInput = $data['input'][0]['content'][0] ?? [];

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $data['model'] === 'gpt-6-luna'
                && $data['reasoning']['effort'] === 'max'
                && $data['store'] === false
                && $data['max_output_tokens'] === 25000
                && array_key_exists('document_type', $data['text']['format']['schema']['properties'])
                && ($fileInput['type'] ?? null) === 'input_file'
                && str_starts_with((string) ($fileInput['file_data'] ?? ''), 'data:application/pdf;base64,');
        });
    }

    public function test_admin_can_refresh_the_ai_request_csrf_token(): void
    {
        $response = $this->actingAs($this->createAdminUser())
            ->getJson(route('admin.ai.csrf-token'));

        $response->assertOk()
            ->assertJsonStructure(['token']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_receipt_stream_reports_actual_model_classification_before_the_complete_result(): void
    {
        $chunks = [
            '{"document_type":"Fuel receipt",',
            '"supplier":"Roadhouse","description":"Diesel","invoice_id":"",',
            '"paid_on":"2026-09-25","date_basis":"transaction_date",',
            '"total_amount":"88.00","gst_amount":"8.00","currency":"AUD",',
            '"evidence":{"supplier":{"page":1,"text":"Roadhouse"},"description":{"page":1,"text":"Diesel"},"invoice_id":{"page":1,"text":""},"paid_on":{"page":1,"text":"25 Sep 2026"},"total_amount":{"page":1,"text":"$88.00"},"gst_amount":{"page":1,"text":"$8.00 GST"}},"needs_review":[]}',
        ];
        $events = collect($chunks)->map(fn (string $delta): string => 'data: '.json_encode([
            'type' => 'response.output_text.delta',
            'delta' => $delta,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n")->implode('');
        $events .= 'data: '.json_encode([
            'type' => 'response.completed',
            'response' => ['status' => 'completed'],
        ])."\n\n";
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($events, 200, ['Content-Type' => 'text/event-stream'])]);

        $response = $this->actingAs($this->createAdminUser())->post(route('admin.ai.expenses.extract.stream'), [
            'receipt_pdf' => UploadedFile::fake()->create('fuel.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'text/event-stream, application/json']);

        $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $stream = $response->streamedContent();
        $this->assertStringContainsString('event: progress', $stream);
        $this->assertStringContainsString('Identified: Fuel receipt', $stream);
        $this->assertStringContainsString('event: result', $stream);
        $this->assertStringContainsString('"document_type":"Fuel receipt"', $stream);

        Http::assertSent(fn (ClientRequest $request): bool => (bool) ($request->data()['stream'] ?? false));
    }

    public function test_incomplete_ai_response_is_reported_as_incomplete_instead_of_an_unreadable_pdf(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'usage' => [
                'output_tokens' => 3000,
                'output_tokens_details' => ['reasoning_tokens' => 3000],
            ],
            'output' => [],
        ], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.expenses.extract'), [
                'receipt_pdf' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI response ran out of room before finishing. Please try again.');
    }

    public function test_ai_provider_errors_return_a_safe_actionable_message(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'error' => ['type' => 'insufficient_quota', 'code' => 'insufficient_quota'],
        ], 429)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.expenses.extract'), [
                'receipt_pdf' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI service is busy or its API quota has been reached. Try again later.');
    }

    public function test_ai_bad_request_includes_the_provider_message_for_diagnosis(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'invalid_type',
                'param' => 'input',
                'message' => 'Invalid type for input. Expected an array.',
            ],
        ], 400)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.newsletter.message'), ['mode' => 'replace'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI service rejected the request: Invalid type for input. Expected an array.');
    }

    public function test_ai_request_extends_a_finite_php_execution_limit_past_the_provider_timeout(): void
    {
        $originalLimit = (int) ini_get('max_execution_time');
        $changedLimit = ini_set('max_execution_time', '30');
        if ($changedLimit === false) {
            $this->markTestSkipped('The PHP execution limit cannot be changed in this environment.');
        }

        config(['services.openai.timeout' => 180]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'title' => 'Robotics Starter Kit',
            'short_description' => 'A hands-on kit for building simple robots.',
            'description' => 'Build and test a simple robot.',
        ])], 200)]);

        try {
            $this->actingAs($this->createAdminUser())
                ->postJson(route('admin.ai.products.draft'), ['title' => 'Robotics Starter Kit'])
                ->assertOk();

            $this->assertGreaterThanOrEqual(210, (int) ini_get('max_execution_time'));
        } finally {
            set_time_limit($originalLimit);
        }
    }

    public function test_product_copy_draft_uses_the_structured_response_and_does_not_save_the_product(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'title' => 'Robotics Starter Kit',
            'short_description' => 'A hands-on kit for building simple robots.',
            'description' => "Explore robotics with a practical build.\n\nCheck the included parts before use.",
        ])], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.products.draft'), [
                'title' => 'Robotics Starter Kit',
                'short_description' => '',
                'description' => 'Build a simple robot with the kit.',
            ])
            ->assertOk()
            ->assertJsonPath('result.short_description', 'A hands-on kit for building simple robots.')
            ->assertJsonPath('result.description', "Explore robotics with a practical build.\n\nCheck the included parts before use.");

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_warning_draft_can_return_a_short_supported_warning(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'warning' => 'Batteries are not included.',
        ])], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.products.draft'), [
                'kind' => 'warning',
                'title' => 'AAA Battery Holder',
                'short_description' => 'Holds two AAA batteries.',
                'description' => 'Two red and black leads provide a 3V power source.',
                'caution_message' => '',
                'context' => json_encode(['product_type' => 'physical']),
            ])
            ->assertOk()
            ->assertJsonPath('result.warning', 'Batteries are not included.');
    }

    public function test_product_specification_draft_returns_key_value_rows_and_omits_sku(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'product_details' => [
                ['key' => 'Battery type', 'value' => '2 × AAA'],
                ['key' => 'SKU', 'value' => 'AAA-HOLDER-01'],
            ],
        ])], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.products.draft'), [
                'kind' => 'specifications',
                'title' => 'AAA Battery Holder',
                'short_description' => 'Holds two AAA batteries.',
                'description' => 'Two red and black leads provide a 3V power source.',
                'context' => json_encode([
                    'product_type' => 'physical',
                    'product_details' => [['key' => 'Pack size', 'value' => '2 holders']],
                ]),
            ])
            ->assertOk()
            ->assertJsonCount(1, 'result.product_details')
            ->assertJsonPath('result.product_details.0.key', 'Battery type')
            ->assertJsonPath('result.product_details.0.value', '2 × AAA');
    }

    public function test_newsletter_header_draft_uses_the_selected_newsletter_content(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
        $admin = $this->createAdminUser();
        $location = Location::factory()->create(['name' => 'Julia Creek Smart Hub']);
        $media = Media::factory()->create(['user_id' => $admin->id]);
        Workshop::factory()->create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'hero_media_name' => $media->name,
            'title' => 'Stop Motion Animation',
            'summary' => 'Create a short stop-motion film.',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'is_private' => false,
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'subject' => 'Make something brilliant this spring',
            'hero_header' => 'Hands-on STEM this month',
            'hero_cta' => 'Join us for a creative workshop.',
        ])], 200)]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.newsletter.header'), ['content_order' => 'workshops'])
            ->assertOk()
            ->assertJsonPath('result.subject', 'Make something brilliant this spring')
            ->assertJsonPath('result.hero_header', 'Hands-on STEM this month')
            ->assertJsonPath('result.hero_cta', 'Join us for a creative workshop.');

        $requestData = Http::recorded()->last()[0]->data();
        $this->assertSame('low', $requestData['reasoning']['effort']);
        $this->assertIsArray($requestData['input']);
        $this->assertSame('user', $requestData['input'][0]['role']);
        $this->assertIsString($requestData['input'][0]['content']);
        $this->assertStringContainsString('"content_order":"workshops"', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Stop Motion Animation', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Julia Creek Smart Hub', $requestData['input'][0]['content']);
    }

    public function test_newsletter_message_context_includes_recent_and_upcoming_activity_and_configured_holidays(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
        $admin = $this->createAdminUser();
        $location = Location::factory()->create(['name' => 'Townsville Library']);
        $media = Media::factory()->create(['user_id' => $admin->id]);
        $recent = Workshop::factory()->create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'hero_media_name' => $media->name,
            'title' => 'Recent Robotics Workshop',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
        ]);
        Ticket::factory()->create([
            'user_id' => $admin->id,
            'workshop_id' => $recent->id,
            'attended_at' => now()->subDays(3)->addHours(1),
        ]);
        Workshop::factory()->create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'hero_media_name' => $media->name,
            'title' => 'Upcoming Design Workshop',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'is_private' => false,
        ]);
        Product::factory()->create(['title' => 'New Maker Kit', 'created_at' => now()->subDays(2)]);
        SiteOption::query()->updateOrCreate(['name' => 'workshops.school-holidays'], ['value' => '2026-09-20 to 2026-09-29']);
        SiteOption::query()->updateOrCreate(['name' => 'workshops.public-holidays'], ['value' => '2026-10-05 | Local public holiday']);

        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'message' => 'It has been a busy couple of weeks of making. We have more workshops coming up soon.',
        ])], 200)]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.newsletter.message'), [
                'mode' => 'append',
                'personal_note' => ['body' => '<p>We had a creative week.</p>'],
            ])
            ->assertOk()
            ->assertJsonPath('result.message', 'It has been a busy couple of weeks of making. We have more workshops coming up soon.');

        $request = Http::recorded()->last()[0];
        $payload = json_encode($request->data());
        $this->assertSame('low', $request->data()['reasoning']['effort']);
        $this->assertIsArray($request->data()['input']);
        $this->assertSame('user', $request->data()['input'][0]['role']);
        $this->assertIsString($request->data()['input'][0]['content']);
        $this->assertStringContainsString('Recent Robotics Workshop', $payload);
        $this->assertStringContainsString('Upcoming Design Workshop', $payload);
        $this->assertStringContainsString('New Maker Kit', $payload);
        $this->assertStringContainsString('Local public holiday', $payload);
        $this->assertStringContainsString('School holidays', $payload);
        $this->assertStringContainsString('We had a creative week.', $payload);
        $this->assertStringContainsString('without repeating', $request->data()['instructions']);
        $this->assertStringContainsString('James’s own voice', $request->data()['instructions']);
        $this->assertStringContainsString('Do not use the em dash character', $request->data()['instructions']);
    }

    public function test_newsletter_replacement_ends_in_james_voice_with_his_signoff(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'message' => 'We have had a lovely fortnight of making.',
        ])], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.newsletter.message'), ['mode' => 'replace'])
            ->assertOk()
            ->assertJsonPath('result.message', 'We have had a lovely fortnight of making.');

        $instructions = Http::recorded()->last()[0]->data()['instructions'];
        $this->assertStringContainsString('James’s own voice', $instructions);
        $this->assertStringContainsString('Do not use the em dash character', $instructions);
        $this->assertStringContainsString('end with the exact sign-off "- James" on its own final line', $instructions);
    }

    public function test_social_copy_uses_current_workshop_form_values_and_never_publishes(): void
    {
        $admin = $this->createAdminUser();
        $location = Location::factory()->create(['name' => 'Townsville Library']);
        $media = Media::factory()->create(['user_id' => $admin->id]);
        $workshop = Workshop::factory()->create([
            'user_id' => $admin->id,
            'location_id' => $location->id,
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(30)->addHours(2),
            'status' => 'open',
            'hero_media_name' => $media->name,
        ]);

        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'facebook_post' => 'Come build with us in Townsville!',
            'instagram_caption' => 'Make, test and create.',
            'hashtags' => '#STEM #Townsville',
            'image_idea' => 'A close-up of a workshop build in progress.',
        ])], 200)]);

        $this->actingAs($admin)
            ->postJson(route('admin.ai.workshops.social-copy', $workshop), [
                'title' => 'Updated Robotics Workshop',
                'content' => '<p>Build and test a small robot.</p>',
                'starts_at' => now()->addDays(45)->format('Y-m-d\\TH:i'),
                'ends_at' => now()->addDays(45)->addHours(2)->format('Y-m-d\\TH:i'),
                'status' => 'open',
                'location_id' => $location->id,
            ])
            ->assertOk()
            ->assertJsonPath('result.facebook_post', 'Come build with us in Townsville!');

        Http::assertSent(fn (ClientRequest $request): bool => str_contains(json_encode($request->data()), 'Updated Robotics Workshop')
            && ($request->data()['reasoning']['effort'] ?? null) === 'low');
        $this->assertSame('open', $workshop->fresh()->status);
    }

    public function test_blueprint_calendar_action_drafts_four_posts_from_description_with_reusable_placeholders(): void
    {
        $context = [
            'source' => 'blueprint',
            'blueprint' => [
                'name' => 'Cardboard Operation Game',
                'default_title' => 'Build a cardboard Operation game',
                'default_summary' => 'Make a game using a simple circuit.',
                'default_description' => 'Build a cardboard game with foil, wire, lights and buzzers, then test and improve the circuit.',
                'tasks' => [
                    ['name' => 'Social Media: Workshop Announcement', 'notes' => '<p>Something exciting is coming up! Join us for a hands-on STEM workshop.</p>'],
                    ['name' => 'Workshop Packing', 'notes' => 'Pack foil, wire and buzzers.'],
                ],
            ],
            'target' => [
                'social_tasks' => [
                    ['name' => 'Social Media: Workshop Announcement', 'current_content' => '<p>Starter copy</p>'],
                    ['name' => 'Social Media: Before the Workshop', 'current_content' => '<p>Starter copy</p>'],
                    ['name' => 'Social Media: Workshop Day Post', 'current_content' => '<p>Starter copy</p>'],
                    ['name' => 'Social Media: After the Workshop', 'current_content' => '<p>Starter copy</p>'],
                ],
            ],
        ];
        $socialPost = fn (string $hook, string $body, string $invitation, string $emoji = '⚡'): array => [
            'emoji' => $emoji,
            'hook' => $hook,
            'body' => $body,
            'invitation' => $invitation,
            'hashtags' => ['#STEMMechanics', '#OperationGame', '#KidsInSTEM', '#HandsOnLearning'],
        ];
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'announcement' => $socialPost('Let’s build a cardboard Operation game!', 'We’ll turn cardboard, foil and wire into a game that lights up and buzzes when the circuit connects.', 'Come build with us, test your ideas and see what you can make!', '🩺'),
            'before_workshop' => $socialPost('Can you make a cardboard game buzz?', 'We’re getting ready to build an Operation-style board with foil contacts, wire and a simple circuit.', 'Join us to build, test and play along!'),
            'workshop_day' => $socialPost('It’s workshop day, and the circuit challenge is on!', 'Today we’re turning cardboard, foil and wire into a game with lights and buzzers.', 'Come and see what you can build with us!'),
            'after_workshop' => $socialPost('Cardboard, circuits and a classic game challenge!', 'We explored how foil, wire, lights and buzzers can turn a cardboard game into a working circuit.', 'Take a look at the workshop details and find out more about STEMMechanics.'),
        ])], 200)]);

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.workshops.copy'), [
                'kind' => 'social_post_bundle',
                'mode' => 'write',
                'context' => json_encode($context, JSON_THROW_ON_ERROR),
            ])
            ->assertOk()
            ->assertJsonPath('result.announcement', "🩺 Let’s build a cardboard Operation game!\nWe’ll turn cardboard, foil and wire into a game that lights up and buzzes when the circuit connects.\n\n📅 {date-long}\n⏰ {time-range}\n📍 {location}\n🧒 For ages {ages}\n💰 {cost}\n\nCome build with us, test your ideas and see what you can make!\n{workshop-url}\n\n#STEMMechanics #OperationGame #KidsInSTEM #HandsOnLearning")
            ->assertJsonPath('result.workshop_day', "⚡ It’s workshop day, and the circuit challenge is on!\nToday we’re turning cardboard, foil and wire into a game with lights and buzzers.\n\n📅 {date-long}\n⏰ {time-range}\n📍 {location}\n🧒 For ages {ages}\n💰 {cost}\n\nCome and see what you can build with us!\n{workshop-url}\n\n#STEMMechanics #OperationGame #KidsInSTEM #HandsOnLearning")
            ->assertJsonPath('result.after_workshop', "⚡ Cardboard, circuits and a classic game challenge!\nWe explored how foil, wire, lights and buzzers can turn a cardboard game into a working circuit.\n\n📅 {date-long}\n📍 {location}\n\nTake a look at the workshop details and find out more about STEMMechanics.\n{workshop-url}\n\n#STEMMechanics #OperationGame #KidsInSTEM #HandsOnLearning");

        $request = Http::recorded()->last()[0]->data();
        $this->assertSame('low', $request['reasoning']['effort']);
        $this->assertSame(['announcement', 'before_workshop', 'workshop_day', 'after_workshop'], array_keys($request['text']['format']['schema']['properties']));
        $this->assertSame(['emoji', 'hook', 'body', 'invitation', 'hashtags'], array_keys($request['text']['format']['schema']['properties']['announcement']['properties']));
        $this->assertSame('array', $request['text']['format']['schema']['properties']['announcement']['properties']['hashtags']['type']);
        $this->assertStringContainsString('at least two concrete details', $request['instructions']);
        $this->assertStringContainsString('familiar STEMMechanics style', $request['instructions']);
        $this->assertStringContainsString('one relevant Unicode emoji', $request['instructions']);
        $this->assertStringContainsString('three to five relevant hashtags', $request['instructions']);
        $this->assertStringContainsString('link on its own line', $request['instructions']);
        $this->assertStringContainsString('Build a cardboard game', $request['input'][0]['content']);
        $this->assertStringContainsString('Pack foil, wire and buzzers', $request['input'][0]['content']);
        $this->assertStringNotContainsString('Something exciting is coming up', $request['input'][0]['content']);
        $this->assertStringNotContainsString('Starter copy', $request['input'][0]['content']);
        $this->assertStringNotContainsString('current_content', $request['input'][0]['content']);
        $this->assertStringContainsString('{workshop-url}', $request['instructions']);
        $this->assertStringContainsString('“hands-on STEM workshop”', $request['instructions']);
    }

    public function test_workshop_task_wand_receives_current_workshop_and_persisted_blueprint_context(): void
    {
        $admin = $this->createAdminUser();
        $blueprint = PickListTemplate::query()->create([
            'name' => 'Paper Speakers',
            'description' => 'A hands-on sound experiment.',
            'duration' => '90 minutes',
            'participants' => '8-20',
            'default_workshop_title' => 'Build a paper speaker',
            'default_workshop_summary' => 'Make music with a simple speaker.',
            'default_workshop_content' => '<p>Build and test paper speakers.</p>',
            'run_sheet' => '<p>Set out the paper cones and introduce the sound experiment.</p>',
        ]);
        $blueprint->items()->create([
            'item_name' => 'Copper tape',
            'quantity_type' => \App\Models\PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);
        $blueprint->tasks()->create([
            'name' => 'Draft Facebook post',
            'notes' => 'Mention the hands-on build.',
            'sort_order' => 10,
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
            'content' => 'Come make music with us at the library!',
        ])], 200)]);

        $context = [
            'source' => 'workshop',
            'blueprint_id' => $blueprint->id,
            'blueprint' => [
                'id' => $blueprint->id,
                'default_summary' => 'Unsaved blueprint summary from the current form.',
                'run_sheet' => 'Unsaved run sheet details from the current form.',
            ],
            'workshop' => [
                'title' => 'Paper Speakers at Julia Creek',
                'summary' => 'A joyful sound experiment.',
                'description' => 'Make a speaker from everyday materials.',
                'starts_at' => '2026-10-10T10:00',
                'location_id' => 'current-location-id',
                'ages' => '8–12',
                'price' => '15',
                'status' => 'open',
                'registration' => 'tickets',
            ],
            'task_outline' => [['name' => 'Draft Facebook post']],
            'target' => [
                'task_name' => 'Draft Facebook post',
                'current_content' => '',
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.ai.workshops.copy'), [
                'kind' => 'task_content',
                'mode' => 'write',
                'context' => json_encode($context, JSON_THROW_ON_ERROR),
            ])
            ->assertOk()
            ->assertJsonPath('result.content', 'Come make music with us at the library!');

        $requestData = Http::recorded()->last()[0]->data();
        $this->assertIsString($requestData['input'][0]['content']);
        $this->assertStringContainsString('Paper Speakers', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Copper tape', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Draft Facebook post', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Paper Speakers at Julia Creek', $requestData['input'][0]['content']);
        $this->assertStringContainsString('8–12', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Unsaved blueprint summary from the current form.', $requestData['input'][0]['content']);
        $this->assertStringContainsString('Unsaved run sheet details from the current form.', $requestData['input'][0]['content']);
        $this->assertStringContainsString('friendly, ready-to-edit copy', $requestData['instructions']);
        $this->assertStringContainsString('do not invent facts', $requestData['instructions']);
        foreach (['{date-long}', '{time-range}', '{location}', '{ages}', '{cost}', '{workshop-url}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $requestData['instructions']);
        }
        $this->assertStringContainsString('Do not replace them with current values', $requestData['instructions']);
        $this->assertStringContainsString('ready-to-paste Facebook or Instagram copy', $requestData['instructions']);
    }

    public function test_blueprint_task_wand_uses_placeholders_and_builds_packing_notes_from_materials(): void
    {
        $admin = $this->createAdminUser();
        $blueprint = PickListTemplate::query()->create([
            'name' => 'Paper Speakers',
            'default_workshop_title' => 'Build a paper speaker',
            'default_workshop_content' => '<p>Build and test a paper speaker.</p>',
        ]);
        $blueprint->items()->create([
            'item_name' => 'Copper tape',
            'quantity_type' => \App\Models\PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode(['content' => 'Draft copy.'])], 200)]);

        $context = [
            'source' => 'blueprint',
            'blueprint' => ['id' => $blueprint->id],
            'target' => ['task_name' => 'Social Media: Workshop Announcement', 'current_content' => ''],
        ];
        $this->actingAs($admin)
            ->postJson(route('admin.ai.workshops.copy'), [
                'kind' => 'task_content',
                'mode' => 'write',
                'context' => json_encode($context, JSON_THROW_ON_ERROR),
            ])
            ->assertOk()
            ->assertJsonPath('result.content', 'Draft copy.');

        $socialInstructions = Http::recorded()->last()[0]->data()['instructions'];
        foreach (['{date-long}', '{time-range}', '{location}', '{ages}', '{cost}', '{workshop-url}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $socialInstructions);
        }
        $this->assertStringContainsString('include {date-long}, {time-range}, {location}, {ages}, {cost}, and {workshop-url}', $socialInstructions);

        $context['target']['task_name'] = 'Workshop Packing';
        $this->postJson(route('admin.ai.workshops.copy'), [
            'kind' => 'task_content',
            'mode' => 'write',
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
        ])->assertOk();

        $packingRequest = Http::recorded()->last()[0]->data();
        $this->assertStringContainsString('practical packing checklist', $packingRequest['instructions']);
        $this->assertStringContainsString('Copper tape', $packingRequest['input'][0]['content']);
    }

    public function test_workshop_summary_and_amend_wands_use_description_and_ground_learning_outcomes(): void
    {
        $admin = $this->createAdminUser();
        $blueprint = PickListTemplate::query()->create([
            'name' => 'Cardboard Operation Game',
            'default_workshop_title' => 'Build a cardboard Operation game',
            'default_workshop_summary' => '',
            'default_workshop_content' => '<p>Build a cardboard game using foil, wire, lights and buzzers.</p>',
            'run_sheet' => '<p>Test the circuit and fix connection problems.</p>',
        ]);
        $blueprint->items()->create([
            'item_name' => 'Aluminium foil',
            'quantity_type' => \App\Models\PickListTemplateItem::TYPE_PER_PARTICIPANT,
            'quantity_value' => 1,
            'sort_order' => 10,
        ]);
        $blueprint->tasks()->create([
            'name' => 'Test and improve game circuits',
            'notes' => 'Identify and fix loose connections.',
            'sort_order' => 10,
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => function (ClientRequest $request) {
            $instructions = (string) ($request->data()['instructions'] ?? '');
            $inputContent = (string) data_get($request->data(), 'input.0.content', '');
            $content = match (true) {
                str_contains($instructions, 'concise STEMMechanics workshop summary') => 'Build a cardboard game that brings simple circuits to life.',
                str_contains($instructions, 'current public description already has a Learning outcomes section') && str_contains($inputContent, 'Conductors and insulators') => 'NO_CHANGES',
                str_contains($instructions, 'current public description does not have a Learning outcomes section') => "### Learning outcomes\n\n- **Electrical circuits:** Understand how electricity flows through a complete circuit.\n- **Conductive materials:** Explore how foil and wire can carry electricity.\n- **Inputs and outputs:** Discover how a completed circuit can trigger light and sound.\n- **Testing and problem-solving:** Test a design, identify faults and improve how it works.",
                default => "Build a cardboard game that brings simple circuits to life.\n\n## Learning outcomes\n\n- **Electrical circuits:** Explore how a complete circuit works.",
            };

            return Http::response(['output_text' => json_encode(['content' => $content])], 200);
        }]);

        $blueprintContext = [
            'source' => 'blueprint',
            'blueprint' => [
                'id' => $blueprint->id,
                'default_summary' => '',
                'default_description' => 'Build a cardboard game using foil, wire, lights and buzzers.',
            ],
        ];
        $workshopContext = [
            'source' => 'workshop',
            'blueprint_id' => $blueprint->id,
            'workshop' => [
                'title' => 'Build a cardboard Operation game',
                'summary' => 'A cardboard electronics game.',
                'description' => 'Participants make a cardboard game with foil, wire, lights and buzzers, then test and improve the circuit.',
            ],
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.ai.workshops.copy'), [
                'kind' => 'workshop_summary',
                'mode' => 'write',
                'context' => json_encode($blueprintContext, JSON_THROW_ON_ERROR),
            ])
            ->assertOk()
            ->assertJsonPath('result.content', 'Build a cardboard game that brings simple circuits to life.');

        $this->postJson(route('admin.ai.workshops.copy'), [
            'kind' => 'workshop_description_amend',
            'mode' => 'improve',
            'context' => json_encode($workshopContext, JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('result.content', "Build a cardboard game that brings simple circuits to life.\n\n## Learning outcomes\n\n- **Electrical circuits:** Explore how a complete circuit works.");

        $this->postJson(route('admin.ai.workshops.copy'), [
            'kind' => 'blueprint_description_amend',
            'mode' => 'improve',
            'context' => json_encode($blueprintContext, JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('result.content', "### Learning outcomes\n\n- **Electrical circuits:** Understand how electricity flows through a complete circuit.\n- **Conductive materials:** Explore how foil and wire can carry electricity.\n- **Inputs and outputs:** Discover how a completed circuit can trigger light and sound.\n- **Testing and problem-solving:** Test a design, identify faults and improve how it works.");

        $completeBlueprintContext = $blueprintContext;
        $completeBlueprintContext['blueprint']['default_description'] = <<<'DESCRIPTION'
Build a cardboard game using foil, wire, lights and buzzers.

Learning outcomes

- Electrical circuits - Understand how current flows through a complete circuit and what happens when a circuit is opened or closed.
- Conductors and insulators - Explore which materials conduct electricity and learn why aluminium foil and wire can be used to create electrical connections.
- Inputs and outputs - Discover how contact between game components acts as an input that triggers outputs such as lights and sound.
- Engineering and construction - Design and assemble a working game board using everyday materials and simple electronic components.
- Problem solving and testing - Test connections, identify faults, and make adjustments to improve the reliability of the game.
- Creativity and game design - Make creative decisions about the game’s theme, appearance, layout, and level of difficulty.
DESCRIPTION;

        $this->postJson(route('admin.ai.workshops.copy'), [
            'kind' => 'blueprint_description_amend',
            'mode' => 'improve',
            'context' => json_encode($completeBlueprintContext, JSON_THROW_ON_ERROR),
        ])
            ->assertOk()
            ->assertJsonPath('result.content', 'NO_CHANGES');

        $requests = Http::recorded()->map(fn (array $pair): array => $pair[0]->data())->values();
        $summaryRequest = $requests[0];
        $amendRequest = $requests[1];
        $blueprintAmendRequest = $requests[2];
        $completeBlueprintAmendRequest = $requests[3];
        $this->assertStringContainsString('concise STEMMechanics workshop summary', $summaryRequest['instructions']);
        $this->assertStringContainsString('Build a cardboard game using foil, wire, lights and buzzers.', $summaryRequest['input'][0]['content']);
        $this->assertStringContainsString('Learning outcomes section', $amendRequest['instructions']);
        $this->assertSame('low', $summaryRequest['reasoning']['effort']);
        $this->assertSame('medium', $amendRequest['reasoning']['effort']);
        $this->assertSame('medium', $blueprintAmendRequest['reasoning']['effort']);
        $this->assertStringContainsString('Aluminium foil', $amendRequest['input'][0]['content']);
        $this->assertStringContainsString('Identify and fix loose connections.', $amendRequest['input'][0]['content']);
        $this->assertStringContainsString('Participants make a cardboard game', $amendRequest['input'][0]['content']);
        $this->assertStringContainsString('current public description does not have a Learning outcomes section', $blueprintAmendRequest['instructions']);
        $this->assertStringContainsString('You MUST create one; do not return NO_CHANGES', $blueprintAmendRequest['instructions']);
        $this->assertStringContainsString('parents and teachers can understand', $blueprintAmendRequest['instructions']);
        $this->assertStringContainsString('do not turn using tweezers, touching foil contacts or assembling a supplied template into separate learning outcomes', $blueprintAmendRequest['instructions']);
        $this->assertStringContainsString('current public description already has a Learning outcomes section', $completeBlueprintAmendRequest['instructions']);
        $this->assertStringContainsString('Otherwise return only bullets for the missing objectives, with no heading', $completeBlueprintAmendRequest['instructions']);
        $this->assertStringContainsString('If the existing outcomes cover the activity’s key learning goals, or there is no clear gap, return exactly NO_CHANGES', $completeBlueprintAmendRequest['instructions']);
        $this->assertStringNotContainsString('Improve the existing content', $blueprintAmendRequest['instructions']);
        $this->assertStringContainsString('Build a cardboard game using foil, wire, lights and buzzers.', $blueprintAmendRequest['input'][0]['content']);
    }

    public function test_ai_endpoints_fail_clearly_when_no_api_key_is_configured(): void
    {
        config(['services.openai.api_key' => '']);
        Http::preventStrayRequests();

        $this->actingAs($this->createAdminUser())
            ->postJson(route('admin.ai.products.draft'), ['title' => 'Robotics Starter Kit'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'AI drafting is not configured yet. Add OPENAI_API_KEY to the server environment.');

        Http::assertNothingSent();
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => (string) $admin->id, 'slug' => 'admin']);

        return $admin;
    }
}
