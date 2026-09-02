<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->timestamp('receipt_document_index_queued_at')->nullable()->after('receipt_document_text');
            $table->timestamp('receipt_document_indexed_at')->nullable()->after('receipt_document_index_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn([
                'receipt_document_index_queued_at',
                'receipt_document_indexed_at',
            ]);
        });
    }
};
