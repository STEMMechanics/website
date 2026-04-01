<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'cleared_at')) {
                $table->timestamp('cleared_at')->nullable()->after('notes')->index();
            }
        });

        if (Schema::hasColumn('payments', 'cleared_at')) {
            DB::table('payments')
                ->whereNull('cleared_at')
                ->where('payment_method', 'bank_transfer')
                ->update([
                    'cleared_at' => DB::raw('COALESCE(received_on, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'cleared_at')) {
                $table->dropIndex(['cleared_at']);
                $table->dropColumn('cleared_at');
            }
        });
    }
};
