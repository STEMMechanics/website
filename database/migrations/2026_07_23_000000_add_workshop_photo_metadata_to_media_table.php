<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('visibility')->default('public')->after('status');
            $table->text('consent_notes')->nullable()->after('visibility');
            $table->text('caption')->nullable()->after('consent_notes');
            $table->string('tags')->nullable()->after('caption');
            $table->timestamp('photographed_at')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn([
                'visibility',
                'consent_notes',
                'caption',
                'tags',
                'photographed_at',
            ]);
        });
    }
};
