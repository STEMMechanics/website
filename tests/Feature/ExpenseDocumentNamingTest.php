<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
