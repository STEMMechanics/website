<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('finance_categories')->where('kind', 'sponsorship')->exists()) {
            DB::table('finance_categories')->insert([
                'name' => 'Sponsorships',
                'kind' => 'sponsorship',
                'priority' => 80,
                'active' => true,
                'opening_cents' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('finance_categories')->where('kind', 'sponsorship')->delete();
    }
};
