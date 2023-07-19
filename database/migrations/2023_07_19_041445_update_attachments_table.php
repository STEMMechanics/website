<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->renameColumn('attachable_type', 'addendum_type');
            $table->renameColumn('attachable_id', 'addendum_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->renameColumn('addendum_type', 'attachable_type');
            $table->renameColumn('addendum_id', 'attachable_id');
        });
    }
};
