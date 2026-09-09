<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_fund_transfers', function (Blueprint $table) {
            $table->foreignUuid('remuneration_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remuneration_user_id');
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
