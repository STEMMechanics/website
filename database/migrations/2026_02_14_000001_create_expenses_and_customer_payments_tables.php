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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier')->nullable();
            $table->string('description')->nullable();
            $table->date('paid_on')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->string('receipt_document_path')->nullable();
            $table->string('receipt_document_name')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['payment', 'refund'])->default('payment')->index();
            $table->foreignId('refund_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_on')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('gateway_provider', 40)->nullable()->index();
            $table->string('gateway_status', 80)->nullable()->index();
            $table->string('gateway_reference_id', 120)->nullable();
            $table->string('square_payment_id', 120)->nullable()->index();
            $table->string('square_order_id', 120)->nullable();
            $table->string('square_location_id', 120)->nullable();
            $table->text('square_receipt_url')->nullable();
            $table->string('square_card_brand', 40)->nullable();
            $table->string('square_card_last4', 4)->nullable();
            $table->unsignedBigInteger('square_paid_money_amount')->nullable();
            $table->unsignedBigInteger('square_refunded_money_amount')->default(0);
            $table->timestamp('square_gateway_created_at')->nullable();
            $table->timestamp('square_gateway_updated_at')->nullable();
            $table->string('square_last_event_type', 120)->nullable();
            $table->string('square_last_event_id', 120)->nullable();
            $table->timestamp('square_last_event_at')->nullable();
            $table->json('square_webhook_payload')->nullable();
            $table->timestamps();
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments AUTO_INCREMENT = 1100');
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER SEQUENCE payments_id_seq RESTART WITH 1100");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('expenses');
    }
};
