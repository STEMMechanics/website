<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minecraft_penalties', function (Blueprint $table): void {
            $table->string('uuid', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('minecraft_penalties', function (Blueprint $table): void {
            $table->string('uuid', 64)->nullable(false)->change();
        });
    }
};
