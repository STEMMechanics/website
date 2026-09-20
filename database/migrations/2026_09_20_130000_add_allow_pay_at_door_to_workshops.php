<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            // Preserve existing venue payment choices; online workshops are always excluded at checkout.
            $table->boolean('allow_pay_at_door')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('allow_pay_at_door');
        });
    }
};
