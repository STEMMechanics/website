<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('written_off_at')->nullable()->after('issued_at');
            $table->text('written_off_reason')->nullable()->after('written_off_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled', 'written_off') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('status', 'written_off')
            ->update(['status' => 'issued']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['written_off_at', 'written_off_reason']);
        });
    }
};
