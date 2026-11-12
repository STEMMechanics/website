<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'invoice_id_normalized')) {
                $table->string('invoice_id_normalized', 120)->nullable()->after('invoice_id');
            }
        });

        if (Schema::hasColumn('expenses', 'invoice_id_normalized')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->index('invoice_id_normalized', 'expenses_invoice_id_normalized_index');
            });
        }

        DB::table('expenses')
            ->select(['id', 'invoice_id'])
            ->whereNotNull('invoice_id')
            ->orderBy('id')
            ->chunkById(500, function ($expenses): void {
                foreach ($expenses as $expense) {
                    $invoiceId = mb_strtolower(trim((string) $expense->invoice_id));
                    $invoiceId = preg_replace('/\s+/u', ' ', $invoiceId) ?: $invoiceId;

                    DB::table('expenses')->where('id', $expense->id)->update([
                        'invoice_id_normalized' => $invoiceId !== '' ? $invoiceId : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'invoice_id_normalized')) {
                $table->dropIndex('expenses_invoice_id_normalized_index');
                $table->dropColumn('invoice_id_normalized');
            }
        });
    }
};
