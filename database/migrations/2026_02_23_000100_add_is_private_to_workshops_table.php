<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->boolean('is_private')->default(false)->after('status');
        });

        DB::table('workshops')
            ->where('status', 'private')
            ->update([
                'is_private' => true,
                'status' => 'open',
            ]);
    }

    public function down(): void
    {
        DB::table('workshops')
            ->where('is_private', true)
            ->where('status', 'open')
            ->update(['status' => 'private']);

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('is_private');
        });
    }
};

