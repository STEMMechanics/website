<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_options')
            ->where(function ($query): void {
                $query->where('name', 'like', 'minecraft.rcon-%')
                    ->orWhere('name', 'like', 'minecraft.management-%');
            })
            ->delete();
    }

    public function down(): void
    {
        // These legacy options are obsolete and are not recreated.
    }
};
