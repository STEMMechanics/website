<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->string('guardian_name')->nullable()->after('surname');
            $table->boolean('media_consent')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->dropColumn(['guardian_name', 'media_consent']);
        });
    }
};
