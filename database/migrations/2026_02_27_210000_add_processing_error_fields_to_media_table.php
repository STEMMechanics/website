<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'last_processing_error')) {
                $table->text('last_processing_error')->nullable()->after('status');
            }
            if (! Schema::hasColumn('media', 'last_processing_failed_at')) {
                $table->timestamp('last_processing_failed_at')->nullable()->after('last_processing_error');
            }
            if (! Schema::hasColumn('media', 'last_processing_batch_id')) {
                $table->string('last_processing_batch_id', 120)->nullable()->after('last_processing_failed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            foreach (['last_processing_error', 'last_processing_failed_at', 'last_processing_batch_id'] as $column) {
                if (Schema::hasColumn('media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

