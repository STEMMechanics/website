<?php

namespace Tests\Feature;

use App\Jobs\IndexSearchableDocument;
use App\Models\Expense;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\PdfTextExtractor;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExpenseDocumentNamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_expense_update_suffixes_attachment_name_when_target_filename_exists(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'STEM Supplies Co',
            'invoice_id' => null,
            'paid_on' => '2026-03-01',
            'total_amount' => 55.00,
            'gst_amount' => 5.00,
        ]);

        $requestInvoiceId = '12345';
        $occupiedPath = 'finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-INV'.$requestInvoiceId.'.pdf';
        Storage::disk('local')->put($occupiedPath, 'existing');

        $response = $this->actingAs($admin)
            ->from(route('admin.expense.edit', $expense))
            ->put(route('admin.expense.update', $expense), [
                'supplier' => 'STEM Supplies Co',
                'description' => 'Updated expense',
                'invoice_id' => $requestInvoiceId,
                'paid_on' => '2026-03-01',
                'total_amount' => '55.00',
                'gst_amount' => '5.00',
                'receipt_document_file' => UploadedFile::fake()->create('receipt.pdf', 12, 'application/pdf'),
            ]);

        $response->assertRedirect(route('admin.expense.edit', $expense));
        $response->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertSame('finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-INV'.$requestInvoiceId.'-1.pdf', $expense->receipt_document_path);
        $this->assertSame('260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-INV'.$requestInvoiceId.'-1.pdf', $expense->receipt_document_name);
        Storage::disk('local')->assertExists($occupiedPath);
        Storage::disk('local')->assertExists((string) $expense->receipt_document_path);
    }

    public function test_expense_attachment_can_be_updated_with_a_real_multipart_post(): void
    {
        Storage::fake('local');

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->with(Mockery::pattern('/\.pdf$/'))
            ->andReturn('ARLEC security light receipt');
        $this->app->instance(PdfTextExtractor::class, $extractor);

        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Mail Attachment Supplier',
            'invoice_id' => 'MAIL-1',
            'paid_on' => '2026-08-28',
            'total_amount' => 44.00,
            'gst_amount' => 4.00,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.expense.edit', $expense))
            ->post(route('admin.expense.update', $expense), [
                'supplier' => 'Mail Attachment Supplier',
                'description' => 'Attachment dropped from iPad Mail',
                'invoice_id' => 'MAIL-1',
                'paid_on' => '2026-08-28',
                'total_amount' => '44.00',
                'gst_amount' => '4.00',
                'receipt_document_file' => UploadedFile::fake()->create('mail-receipt.pdf', 12, 'application/pdf'),
            ]);

        $response->assertRedirect(route('admin.expense.edit', $expense));
        $response->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertNotNull($expense->receipt_document_path);
        $this->assertSame('ARLEC security light receipt', $expense->receipt_document_text);
        Storage::disk('local')->assertExists((string) $expense->receipt_document_path);
    }

    public function test_expense_attachment_ajax_update_returns_a_redirect_payload(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'iPad Mail Supplier',
            'invoice_id' => 'MAIL-2',
            'paid_on' => '2026-08-28',
            'total_amount' => 22.00,
            'gst_amount' => 2.00,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.expense.update', $expense), [
                'supplier' => 'iPad Mail Supplier',
                'description' => 'CID attachment dropped from iPad Mail',
                'invoice_id' => 'MAIL-2',
                'paid_on' => '2026-08-28',
                'total_amount' => '22.00',
                'gst_amount' => '2.00',
                'receipt_document_file' => UploadedFile::fake()->create('cid:attachment-uuid.pdf', 12, 'application/pdf'),
            ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('admin.expense.edit', $expense),
        ]);

        $expense->refresh();
        Storage::disk('local')->assertExists((string) $expense->receipt_document_path);
    }

    public function test_expense_rename_command_suffixes_until_free_filename_is_found(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'STEM Supplies Co',
            'invoice_id' => null,
            'paid_on' => '2026-03-01',
            'receipt_document_path' => 'finance/expenses/legacy-name.pdf',
            'receipt_document_name' => 'legacy-name.pdf',
        ]);

        Storage::disk('local')->put('finance/expenses/legacy-name.pdf', 'legacy');
        Storage::disk('local')->put('finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'.pdf', 'occupied');
        Storage::disk('local')->put('finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-1.pdf', 'occupied-again');

        $this->artisan('expenses:rename-documents')
            ->assertExitCode(0);

        $expense->refresh();

        $this->assertSame('finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-2.pdf', $expense->receipt_document_path);
        $this->assertSame('260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-2.pdf', $expense->receipt_document_name);
        Storage::disk('local')->assertMissing('finance/expenses/legacy-name.pdf');
        Storage::disk('local')->assertExists((string) $expense->receipt_document_path);
    }

    public function test_expense_index_shows_missing_attached_invoice_warning(): void
    {
        $admin = $this->createAdminUser();
        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'STEM Supplies Co',
            'invoice_id' => 'INV-12345',
            'paid_on' => '2026-03-01',
            'total_amount' => 55.00,
            'gst_amount' => 5.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expense.index'));

        $response->assertOk();
        $response->assertSeeText('No attached invoice');
        $response->assertSee('space-y-4 md:hidden', false);
    }

    public function test_expense_index_hides_missing_attached_invoice_warning_when_a_receipt_is_attached(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'STEM Supplies Co',
            'invoice_id' => 'INV-12345',
            'receipt_document_path' => 'finance/expenses/sample.pdf',
            'receipt_document_name' => 'sample.pdf',
            'paid_on' => '2026-03-01',
            'total_amount' => 55.00,
            'gst_amount' => 5.00,
        ]);

        Storage::disk('local')->put('finance/expenses/sample.pdf', 'pdf');

        $response = $this->actingAs($admin)->get(route('admin.expense.index'));

        $response->assertOk();
        $response->assertDontSeeText('No attached invoice');
    }

    public function test_expense_index_distinguishes_a_missing_attachment_file_from_no_attachment(): void
    {
        Storage::fake('local');
        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Offline Storage Supplier',
            'receipt_document_path' => 'finance/expenses/temporarily-offline.pdf',
            'receipt_document_name' => 'temporarily-offline.pdf',
        ]);

        $this->actingAs($admin)->get(route('admin.expense.index'))
            ->assertOk()
            ->assertSeeText('Attachment missing')
            ->assertDontSeeText('No attached invoice');

        $this->actingAs($admin)->post(route('admin.expense.update', $expense), [
            'supplier' => 'Offline Storage Supplier',
            'description' => 'Record remains recoverable',
            'invoice_id' => 'OFFLINE-1',
            'paid_on' => '2026-09-02',
            'total_amount' => '20.00',
            'gst_amount' => '1.82',
        ])->assertSessionHasNoErrors();

        $this->assertSame('finance/expenses/temporarily-offline.pdf', $expense->fresh()->receipt_document_path);
        $this->assertSame('temporarily-offline.pdf', $expense->fresh()->receipt_document_name);
    }

    public function test_expense_index_can_filter_to_expenses_without_attachments(): void
    {
        $admin = $this->createAdminUser();

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'No Attachment Supplier',
            'description' => 'Missing receipt',
            'invoice_id' => 'INV-0001',
            'paid_on' => '2026-03-01',
            'total_amount' => 25.00,
            'gst_amount' => 2.27,
            'receipt_document_path' => null,
            'receipt_document_name' => null,
        ]);

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'With Attachment Supplier',
            'description' => 'Has receipt',
            'invoice_id' => 'INV-0002',
            'paid_on' => '2026-03-02',
            'total_amount' => 75.00,
            'gst_amount' => 6.82,
            'receipt_document_path' => 'finance/expenses/sample.pdf',
            'receipt_document_name' => 'sample.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expense.index', [
            'no_attachment' => 1,
        ]));

        $response->assertOk();
        $response->assertSeeText('No Attachment Supplier');
        $response->assertDontSeeText('With Attachment Supplier');
    }

    public function test_expense_index_can_search_by_total_amount(): void
    {
        $admin = $this->createAdminUser();

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Amount Match Supplier',
            'description' => 'Search should find this expense by total amount',
            'invoice_id' => 'INV-AMOUNT-1',
            'paid_on' => '2026-03-03',
            'total_amount' => 155.25,
            'gst_amount' => 14.11,
        ]);

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Amount Miss Supplier',
            'description' => 'Different amount',
            'invoice_id' => 'INV-AMOUNT-2',
            'paid_on' => '2026-03-04',
            'total_amount' => 42.00,
            'gst_amount' => 3.82,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expense.index', [
            'search' => '155.25',
        ]));

        $response->assertOk();
        $response->assertSeeText('Amount Match Supplier');
        $response->assertDontSeeText('Amount Miss Supplier');
    }

    public function test_expense_index_can_search_by_gst_amount(): void
    {
        $admin = $this->createAdminUser();

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'GST Match Supplier',
            'description' => 'Search should find this expense by GST amount',
            'invoice_id' => 'INV-GST-1',
            'paid_on' => '2026-03-05',
            'total_amount' => 88.00,
            'gst_amount' => 7.92,
        ]);

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'GST Miss Supplier',
            'description' => 'Different GST amount',
            'invoice_id' => 'INV-GST-2',
            'paid_on' => '2026-03-06',
            'total_amount' => 88.00,
            'gst_amount' => 8.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expense.index', [
            'search' => '7.92',
        ]));

        $response->assertOk();
        $response->assertSeeText('GST Match Supplier');
        $response->assertDontSeeText('GST Miss Supplier');
    }

    public function test_expense_index_can_search_extracted_attachment_text(): void
    {
        $admin = $this->createAdminUser();

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Attachment Match Supplier',
            'description' => 'Electrical supplies',
            'invoice_id' => 'INV-PDF-1',
            'receipt_document_text' => 'ARLEC twin-head security light and sensor',
        ]);

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Attachment Miss Supplier',
            'description' => 'Electrical supplies',
            'invoice_id' => 'INV-PDF-2',
            'receipt_document_text' => 'Outdoor extension lead',
        ]);

        $this->actingAs($admin)->get(route('admin.expense.index', [
            'search' => 'arlec',
        ]))
            ->assertOk()
            ->assertDontSeeText('Attachment Match Supplier');

        $response = $this->actingAs($admin)->get(route('admin.expense.index', [
            'attachment' => 'arlec',
        ]));

        $response->assertOk();
        $response->assertSeeText('Attachment Match Supplier');
        $response->assertDontSeeText('Attachment Miss Supplier');
    }

    public function test_empty_advanced_search_uses_an_advanced_filter_message(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)->get(route('admin.expense.index', [
            'attachment' => 'term-that-does-not-exist',
        ]))
            ->assertOk()
            ->assertSeeText("We couldn't find any expenses matching the advanced filters.")
            ->assertDontSeeText('matching "term-that-does-not-exist"');
    }

    public function test_expense_document_text_command_backfills_existing_attachments(): void
    {
        Queue::fake();
        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'receipt_document_path' => 'finance/expenses/existing.pdf',
            'receipt_document_text' => null,
        ]);

        $this->artisan('search:index-documents')
            ->expectsOutput('Queued 1 document(s) for search indexing.')
            ->assertExitCode(0);

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->with('finance/expenses/existing.pdf')
            ->andReturn('ARLEC product details');

        Queue::assertPushed(IndexSearchableDocument::class, fn (IndexSearchableDocument $job): bool => $job->documentType === IndexSearchableDocument::TYPE_EXPENSE && $job->documentId === $expense->id);
        $this->assertNotNull($expense->fresh()->receipt_document_index_queued_at);

        (new IndexSearchableDocument(IndexSearchableDocument::TYPE_EXPENSE, (int) $expense->id))->handle($extractor);

        $expense->refresh();
        $this->assertSame('ARLEC product details', $expense->receipt_document_text);
        $this->assertNull($expense->receipt_document_index_queued_at);
        $this->assertNotNull($expense->receipt_document_indexed_at);
    }

    public function test_expense_document_text_command_does_not_requeue_existing_text(): void
    {
        Queue::fake();
        $admin = $this->createAdminUser();
        Expense::factory()->create([
            'created_by' => $admin->id,
            'receipt_document_path' => 'finance/expenses/already-indexed.pdf',
            'receipt_document_text' => 'Existing extracted text',
            'receipt_document_indexed_at' => null,
        ]);

        $this->artisan('search:index-documents')
            ->expectsOutput('Queued 0 document(s) for search indexing.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_selected_expenses_and_attachments_can_be_exported_as_zip(): void
    {
        Storage::fake('local');
        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'ZIP Supplier',
            'receipt_document_path' => 'finance/expenses/receipt.pdf',
            'receipt_document_name' => 'receipt.pdf',
        ]);
        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'Not Exported Supplier',
        ]);
        Storage::disk('local')->put('finance/expenses/receipt.pdf', 'PDF attachment contents');

        $response = $this->actingAs($admin)->post(route('admin.expense.export.zip'), [
            'expense_ids' => [$expense->id],
        ]);

        $response->assertOk();
        $zipPath = $response->baseResponse->getFile()->getPathname();
        $downloadName = (string) $response->headers->get('content-disposition');
        preg_match('/filename=expenses-(\d{8}-\d{6})\.zip/', $downloadName, $matches);
        $exportFolder = 'expenses-'.($matches[1] ?? '');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertMatchesRegularExpression('/^expenses-\d{8}-\d{6}$/', $exportFolder);
        $this->assertNotFalse($zip->locateName($exportFolder.'/expenses.csv'));
        $this->assertNotFalse($zip->locateName($exportFolder.'/attachments/EXP'.$expense->id.'-receipt.pdf'));
        $this->assertStringContainsString('ZIP Supplier', (string) $zip->getFromName($exportFolder.'/expenses.csv'));
        $this->assertStringNotContainsString('Not Exported Supplier', (string) $zip->getFromName($exportFolder.'/expenses.csv'));
        $zip->close();
        @unlink($zipPath);
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
