<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organisation_user')
            ->whereNull('role')
            ->update(['role' => 'member']);
    }

    public function down(): void
    {
        DB::table('organisation_user')
            ->where('role', 'member')
            ->update(['role' => null]);
    }
};
