<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('cost');
            $table->unsignedInteger('priority')->default(50);
            $table->boolean('active')->default(true);
            $table->bigInteger('opening_cents')->default(0);
            $table->timestamps();
        });
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->date('opening_date')->nullable();
            $table->bigInteger('opening_cash_cents')->default(0);
            $table->bigInteger('opening_gst_cents')->default(0);
            $table->bigInteger('buffer_cents')->default(0);
            $table->date('fortnight_anchor')->default('2026-07-06');
            $table->boolean('auto_budget')->default(false);
            $table->timestamps();
        });
        Schema::create('finance_pricing_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_from')->index();
            $table->json('rules');
            $table->json('prices');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('finance_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('workshop_id')->nullable()->unique();
            $table->foreign('workshop_id')->references('id')->on('workshops')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('finance_batches')->restrictOnDelete();
            $table->foreignId('pricing_version_id')->constrained('finance_pricing_versions')->restrictOnDelete();
            $table->string('name');
            $table->date('date')->index();
            $table->json('assumptions');
            $table->json('targets');
            $table->boolean('manual')->default(false);
            $table->timestamps();
        });
        Schema::create('finance_budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('finance_budgets')->cascadeOnDelete();
            $table->json('before');
            $table->json('after');
            $table->timestamps();
        });
        Schema::create('finance_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->string('description');
            $table->date('due_on');
            $table->bigInteger('cents');
            $table->string('status')->default('open');
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('finance_fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_category_id')->nullable()->constrained('finance_categories')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained('finance_budgets')->restrictOnDelete();
            $table->bigInteger('cents');
            $table->string('reason');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('finance_budget_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('finance_budgets')->cascadeOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
        });
        Schema::create('finance_supplier_rules', function (Blueprint $table) {
            $table->id();
            $table->string('supplier')->unique();
            $table->string('mode')->default('default');
            $table->json('splits');
            $table->timestamps();
        });
        Schema::create('finance_expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->foreignId('budget_id')->nullable()->constrained('finance_budgets')->nullOnDelete();
            $table->bigInteger('cents');
            $table->unique(['expense_id', 'category_id']);
            $table->timestamps();
        });
        Schema::create('finance_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('workshop_id')->nullable();
            $table->foreign('workshop_id')->references('id')->on('workshops')->nullOnDelete();
            $table->date('date')->index();
            $table->string('activity');
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('rate_cents');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
        });
        Schema::create('finance_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->uuid('token')->unique();
            $table->bigInteger('cents');
            $table->string('status')->default('pending');
            $table->date('paid_on')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });
        Schema::create('finance_gst_settlements', function (Blueprint $table) {
            $table->id();
            $table->date('period')->unique();
            $table->date('paid_on');
            $table->bigInteger('cents');
            $table->string('reference');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        $now = now();
        foreach ([['Venue hire', 'cost', 10], ['Consumables', 'cost', 20], ['Vehicle', 'cost', 30], ['Insurance', 'cost', 40], ['Operational', 'cost', 50], ['Owner remuneration', 'owner', 60], ['Equipment replacement', 'cost', 70]] as [$name, $kind, $priority]) {
            DB::table('finance_categories')->insert(compact('name', 'kind', 'priority') + ['created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('finance_settings')->insert(['id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('finance_pricing_versions')->insert([
            'name' => '2026–27 starting rates', 'effective_from' => '2026-07-01',
            'rules' => json_encode([
                ['category_id' => 1, 'basis' => 'venue', 'rate_cents' => 5000, 'extra_cents' => 4000],
                ['category_id' => 2, 'basis' => 'participant', 'rate_cents' => 500],
                ['category_id' => 3, 'basis' => 'travel', 'rate_cents' => 1900],
                ['category_id' => 4, 'basis' => 'workshop', 'rate_cents' => 1700],
                ['category_id' => 5, 'basis' => 'workshop', 'rate_cents' => 1000],
                ['category_id' => 6, 'basis' => 'hour', 'rate_cents' => 6000],
                ['category_id' => 6, 'basis' => 'travel', 'rate_cents' => 1500],
                ['category_id' => 7, 'basis' => 'workshop', 'rate_cents' => 750],
            ]),
            'prices' => json_encode(['public' => [1950, 2950, 3950, 4950], 'organisation' => [1450, 2250, 3050, 3850], 'travel_cents' => 3400, 'travel_free_minutes' => 30]),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        foreach (['finance_gst_settlements', 'finance_drawings', 'finance_time_entries', 'finance_expense_splits', 'finance_supplier_rules', 'finance_fund_transfers', 'finance_budget_invoices', 'finance_budget_revisions', 'finance_commitments', 'finance_budgets', 'finance_batches', 'finance_pricing_versions', 'finance_settings', 'finance_categories'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
