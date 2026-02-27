<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'invoice_id')) {
                $table->string('invoice_id', 120)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'invoice_id')) {
                $table->dropColumn('invoice_id');
            }
        });
    }
};

