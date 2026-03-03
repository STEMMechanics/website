<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('is_whitelisted');
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_accounts', function (Blueprint $table): void {
            $table->dropColumn('admin_notes');
        });
    }
};
