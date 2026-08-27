<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('newsletter_store_themes')
            ->where('name', 'Grab Extras')
            ->where('title', 'Grab extras')
            ->update([
                'title' => 'Grab some extras',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('newsletter_store_themes')
            ->where('name', 'Grab Extras')
            ->where('title', 'Grab some extras')
            ->update([
                'title' => 'Grab extras',
                'updated_at' => now(),
            ]);
    }
};
