<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('account_terms_days')->default(0);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('use_organisation_account_terms')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('use_organisation_account_terms'));
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn('account_terms_days'));
    }
};
