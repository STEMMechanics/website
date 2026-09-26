<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_interests', function (Blueprint $table): void {
            $table->timestamp('two_day_reminder_queued_at')->nullable();
            $table->timestamp('two_day_reminder_sent_at')->nullable();
            $table->timestamp('two_hour_reminder_queued_at')->nullable();
            $table->timestamp('two_hour_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_interests', function (Blueprint $table): void {
            $table->dropColumn([
                'two_day_reminder_queued_at',
                'two_day_reminder_sent_at',
                'two_hour_reminder_queued_at',
                'two_hour_reminder_sent_at',
            ]);
        });
    }
};
