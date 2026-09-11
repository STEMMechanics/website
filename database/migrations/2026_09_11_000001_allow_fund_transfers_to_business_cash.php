<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_fund_transfers', function (Blueprint $table) {
            // Null denotes business cash, just as it does for the source.
            $table->unsignedBigInteger('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_transfers', function (Blueprint $table) {
            // Existing cash transfers must be retained; rollback fails if any exist.
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });
    }
};
