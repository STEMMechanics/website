<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminAiController;
use App\Jobs\ExtractExpenseImportItem;
use App\Models\Expense;
use App\Services\ExpenseImportBatchService;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpenseDuplicateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_duplicate_invoice_endpoint_matches_normalised_invoice_ids_and_excludes_current_expense(): void
    {
        $admin = $this->createAdmin();
        $existing = Expense::factory()->create([
            'supplier' => 'Acme Supplies',
            'invoice_id' => 'INV-42',
            'paid_on' => '2026-11-01',
            'total_amount' => 110,
            'gst_amount' => 10,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.expense.duplicate-check', [
                'supplier' => ' ACME SUPPLIES ',
                'invoice_id' => ' inv-42 ',
            ]))
            ->assertOk()
            ->assertJsonPath('matches.0.id', $existing->id)
            ->assertJsonPath('matches.0.supplier', 'Acme Supplies')
            ->assertJsonPath('matches.0.invoice_id', 'INV-42');

        $this->actingAs($admin)
            ->getJson(route('admin.expense.duplicate-check', [
                'supplier' => 'Acme Supplies',
                'invoice_id' => 'INV-42',
                'expense_id' => $existing->id,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'matches');
    }

    public function test_duplicate_invoice_is_blocked_without_an_override(): void
    {
        $admin = $this->createAdmin();
        Expense::factory()->create([
            'supplier' => 'Acme Supplies',
            'invoice_id' => 'INV-42',
            'total_amount' => 110,
            'gst_amount' => 10,
        ]);
        $payload = [
            'supplier' => 'Acme Supplies',
            'description' => 'Replacement receipt',
            'invoice_id' => 'INV-42',
            'paid_on' => '2026-11-02',
            'total_amount' => 110,
            'gst_amount' => 10,
            'batch_import' => 1,
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.expense.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice_id');

        $this->assertDatabaseCount('expenses', 1);

        $this->actingAs($admin)
            ->postJson(route('admin.expense.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice_id');

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_receipt_batch_upload_opens_the_normal_editor_and_advances_after_each_save(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.expense.import.start'), [
                'files' => [
                    UploadedFile::fake()->image('first-receipt.jpg', 80, 80),
                    UploadedFile::fake()->image('second-receipt.png', 80, 80),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('count', 2);
        Queue::assertPushed(ExtractExpenseImportItem::class, 2);

        $firstEditorUrl = $response->json('redirect');
        parse_str((string) parse_url($firstEditorUrl, PHP_URL_QUERY), $firstQuery);
        $batchId = (string) ($firstQuery['import_batch'] ?? '');
        $firstItemId = (string) ($firstQuery['import_item'] ?? '');
        $this->assertNotSame('', $batchId);
        $this->assertNotSame('', $firstItemId);

        $this->actingAs($admin)
            ->get(route('admin.expense.import.file', [$batchId, $firstItemId]))
            ->assertOk();
        Queue::assertPushed(ExtractExpenseImportItem::class, 2);

        $this->actingAs($admin)
            ->get($firstEditorUrl)
            ->assertOk()
            ->assertSee('Record Expense · 1/2')
            ->assertSee('Save and Next')
            ->assertSee('Skip and Next')
            ->assertDontSee('duplicate_confirmation')
            ->assertSee('data-expense-import-file-url', false);

        $firstSave = $this->actingAs($admin)
            ->postJson(route('admin.expense.store'), [
                'supplier' => 'Batch Supplier',
                'description' => 'First staged receipt',
                'invoice_id' => 'BATCH-1',
                'paid_on' => '2026-11-12',
                'total_amount' => 110,
                'gst_amount' => 10,
                'expense_import_batch' => $batchId,
                'expense_import_item' => $firstItemId,
                'batch_import' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $secondEditorUrl = $firstSave->json('redirect');
        $this->assertStringContainsString('import_batch='.$batchId, $secondEditorUrl);
        $this->assertStringNotContainsString('import_item='.$firstItemId, $secondEditorUrl);
        $this->assertDatabaseHas('expenses', ['invoice_id' => 'BATCH-1']);

        $this->actingAs($admin)
            ->get($secondEditorUrl)
            ->assertOk()
            ->assertSee('Record Expense · 2/2')
            ->assertSee('>Save<', false)
            ->assertSee('>Skip<', false)
            ->assertDontSee('Save and Next');

        parse_str((string) parse_url($secondEditorUrl, PHP_URL_QUERY), $secondQuery);
        $finalSave = $this->actingAs($admin)
            ->postJson(route('admin.expense.store'), [
                'supplier' => 'Batch Supplier',
                'description' => 'Second staged receipt',
                'invoice_id' => 'BATCH-2',
                'paid_on' => '2026-11-12',
                'total_amount' => 220,
                'gst_amount' => 20,
                'expense_import_batch' => $batchId,
                'expense_import_item' => $secondQuery['import_item'],
                'batch_import' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.expense.index'));

        $this->assertDatabaseCount('expenses', 2);
        $this->assertNotNull($finalSave->json('expense_id'));
    }

    public function test_background_expense_job_stores_a_completed_result_for_the_editor(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $batch = app(ExpenseImportBatchService::class)->create((string) $admin->id, [
            UploadedFile::fake()->image('background-receipt.jpg', 80, 80),
        ]);
        $item = $batch['items'][0];
        $result = [
            'document_type' => 'Receipt',
            'supplier' => 'Background Supplier',
            'invoice_id' => 'BG-1',
            'product_purchases' => [],
        ];
        $ai = $this->mock(AdminAiController::class);
        $ai->shouldReceive('extractExpenseFile')
            ->once()
            ->andReturn($result);

        (new ExtractExpenseImportItem($batch['id'], $item['id'], (string) $admin->id))
            ->handle(app(ExpenseImportBatchService::class), $ai);

        $status = app(ExpenseImportBatchService::class)->aiItemForUser($batch['id'], $item['id'], (string) $admin->id);
        $this->assertSame('completed', $status['item']['ai_status']);
        $this->assertSame($result, $status['item']['ai_result']);
    }

    public function test_batch_skip_discards_the_current_receipt_and_advances_without_saving(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.expense.import.start'), [
                'files' => [
                    UploadedFile::fake()->image('skip-first.jpg', 80, 80),
                    UploadedFile::fake()->image('skip-second.jpg', 80, 80),
                ],
            ], ['Accept' => 'application/json'])
            ->assertOk();
        parse_str((string) parse_url((string) $response->json('redirect'), PHP_URL_QUERY), $query);

        $skip = $this->actingAs($admin)
            ->postJson(route('admin.expense.import.skip', [$query['import_batch'], $query['import_item']]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $nextUrl = (string) $skip->json('redirect');
        $this->assertStringContainsString('import_batch='.$query['import_batch'], $nextUrl);
        $this->assertStringNotContainsString('import_item='.$query['import_item'], $nextUrl);
        $this->actingAs($admin)
            ->get(route('admin.expense.import.file', [$query['import_batch'], $query['import_item']]))
            ->assertNotFound();
        $this->assertDatabaseCount('expenses', 0);

        parse_str((string) parse_url($nextUrl, PHP_URL_QUERY), $nextQuery);
        $this->actingAs($admin)
            ->postJson(route('admin.expense.import.skip', [$query['import_batch'], $nextQuery['import_item']]))
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.expense.index'));

        $this->assertDatabaseCount('expenses', 0);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);

        return $admin;
    }
}
