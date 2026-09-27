<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('square_refund_operations', function (Blueprint $table): void {
            if (! Schema::hasColumn('square_refund_operations', 'notification_silenced_at')) {
                $table->timestamp('notification_silenced_at')->nullable()->after('processed_at');
            }

            if (! Schema::hasColumn('square_refund_operations', 'notification_silenced_by')) {
                $table->unsignedBigInteger('notification_silenced_by')->nullable()->after('notification_silenced_at');
            }
        });

        if (! Schema::hasIndex('square_refund_operations', 'square_refund_ops_alert_index')) {
            Schema::table('square_refund_operations', function (Blueprint $table): void {
                $table->index(['status', 'notification_silenced_at'], 'square_refund_ops_alert_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('square_refund_operations', 'square_refund_ops_alert_index')) {
            Schema::table('square_refund_operations', function (Blueprint $table): void {
                $table->dropIndex('square_refund_ops_alert_index');
            });
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('square_refund_operations', 'notification_silenced_by') ? 'notification_silenced_by' : null,
            Schema::hasColumn('square_refund_operations', 'notification_silenced_at') ? 'notification_silenced_at' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('square_refund_operations', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
