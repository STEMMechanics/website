<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('square_refund_operations', 'notification_silenced_by')) {
            Schema::table('square_refund_operations', function (Blueprint $table): void {
                $table->uuid('notification_silenced_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('square_refund_operations', 'notification_silenced_by')) {
            Schema::table('square_refund_operations', function (Blueprint $table): void {
                $table->unsignedBigInteger('notification_silenced_by')->nullable()->change();
            });
        }
    }
};
