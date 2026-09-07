<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('finance_settings')->where('id', 1)->first();
        foreach (['finance.fortnight-start' => $settings->fortnight_anchor ?? '2026-07-06', 'finance.cash-buffer' => number_format(($settings->buffer_cents ?? 0) / 100, 2, '.', '')] as $name => $value) {
            DB::table('site_options')->insertOrIgnore(['name' => $name, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep configured values when rolling back application code.
    }
};
