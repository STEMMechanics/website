<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'firstname')) {
                $table->string('firstname')->nullable();
            }
            if (! Schema::hasColumn('tickets', 'surname')) {
                $table->string('surname')->nullable();
            }
            if (! Schema::hasColumn('tickets', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('tickets', 'phone')) {
                $table->string('phone')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'firstname')) {
                $table->dropColumn('firstname');
            }
            if (Schema::hasColumn('tickets', 'surname')) {
                $table->dropColumn('surname');
            }
            if (Schema::hasColumn('tickets', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('tickets', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
