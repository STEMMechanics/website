<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key', 60)->unique();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->enum('status', ['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('issue_date');
            $table->timestamp('issued_at')->nullable();
            $table->date('due_date')->nullable();
            $table->string('purchase_order_number')->nullable();
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('kind', 40)->default('generic');
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->json('details_json')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price_ex_tax', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0.1000);
            $table->decimal('line_total_ex_tax', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total_inc_tax', 10, 2)->default(0);
            $table->nullableMorphs('source');
            $table->foreignId('original_invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);
        });

        Schema::create('invoice_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('tax_adjustment_id')->nullable();
            $table->decimal('allocated_amount', 10, 2);
            $table->timestamps();

            $table->index('tax_adjustment_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
            }
            if (! Schema::hasColumn('tickets', 'invoice_line_id')) {
                $table->unsignedBigInteger('invoice_line_id')->nullable()->index();
            }
        });

        $addInvoiceForeign = Schema::hasColumn('tickets', 'invoice_id')
            && ! $this->hasForeignKey('tickets', 'tickets_invoice_id_foreign');
        $addInvoiceLineForeign = Schema::hasColumn('tickets', 'invoice_line_id')
            && ! $this->hasForeignKey('tickets', 'tickets_invoice_line_id_foreign');

        Schema::table('tickets', function (Blueprint $table) use ($addInvoiceForeign, $addInvoiceLineForeign) {
            if ($addInvoiceForeign) {
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            }
            if ($addInvoiceLineForeign) {
                $table->foreign('invoice_line_id')->references('id')->on('invoice_lines')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dropInvoiceForeign = $this->hasForeignKey('tickets', 'tickets_invoice_id_foreign');
        $dropInvoiceLineForeign = $this->hasForeignKey('tickets', 'tickets_invoice_line_id_foreign');

        Schema::table('tickets', function (Blueprint $table) use ($dropInvoiceForeign, $dropInvoiceLineForeign) {
            if ($dropInvoiceForeign) {
                $table->dropForeign(['invoice_id']);
            }
            if ($dropInvoiceLineForeign) {
                $table->dropForeign(['invoice_line_id']);
            }
        });

        Schema::dropIfExists('invoice_payment_allocations');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('document_sequences');
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
