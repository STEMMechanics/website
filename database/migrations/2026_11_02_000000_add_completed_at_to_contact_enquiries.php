<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropColumn('completed_at');
        });
    }
};
