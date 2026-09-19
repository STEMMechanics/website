<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('site_options')->insertOrIgnore([
                'name' => 'finance.owner-hourly-rate', 'value' => '40.00',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Preserve the configured rate when rolling back application code.
    }
};
