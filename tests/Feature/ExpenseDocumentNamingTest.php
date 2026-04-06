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

        $occupiedPath = 'finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'.pdf';
        Storage::disk('local')->put($occupiedPath, 'existing');

        $response = $this->actingAs($admin)
            ->from(route('admin.expense.edit', $expense))
            ->put(route('admin.expense.update', $expense), [
                'supplier' => 'STEM Supplies Co',
                'description' => 'Updated expense',
                'invoice_id' => '',
                'paid_on' => '2026-03-01',
                'total_amount' => '55.00',
                'gst_amount' => '5.00',
                'receipt_document_file' => UploadedFile::fake()->create('receipt.pdf', 12, 'application/pdf'),
            ]);

        $response->assertRedirect(route('admin.expense.edit', $expense));
        $response->assertSessionHasNoErrors();

        $expense->refresh();

        $this->assertSame('finance/expenses/260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-1.pdf', $expense->receipt_document_path);
        $this->assertSame('260301-STEM-SUPPLIES-CO-EXP'.$expense->id.'-1.pdf', $expense->receipt_document_name);
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
