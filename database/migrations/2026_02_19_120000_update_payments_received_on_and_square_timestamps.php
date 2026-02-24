<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'square_gateway_created_at')) {
                $table->timestamp('square_gateway_created_at')->nullable()->after('square_refunded_money_amount');
            }
            if (! Schema::hasColumn('payments', 'square_gateway_updated_at')) {
                $table->timestamp('square_gateway_updated_at')->nullable()->after('square_gateway_created_at');
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY received_on DATETIME NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN received_on TYPE TIMESTAMP USING received_on::timestamp');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY received_on DATE NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN received_on TYPE DATE USING received_on::date');
        }

        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'square_gateway_updated_at')) {
                $table->dropColumn('square_gateway_updated_at');
            }
            if (Schema::hasColumn('payments', 'square_gateway_created_at')) {
                $table->dropColumn('square_gateway_created_at');
            }
        });
    }
};

