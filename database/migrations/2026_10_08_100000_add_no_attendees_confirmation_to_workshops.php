<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workshops', 'attendance_no_attendees_confirmed_at')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->timestamp('attendance_no_attendees_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workshops', 'attendance_no_attendees_confirmed_at')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->dropColumn('attendance_no_attendees_confirmed_at');
            });
        }
    }
};
