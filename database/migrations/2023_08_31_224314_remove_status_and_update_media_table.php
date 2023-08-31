<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete rows where the status is not 'OK'
        DB::table('media')->where('status', '<>', 'OK')->delete();

        // Remove the 'status' column from the 'media' table
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add the 'status' column back with a default value of an empty string
        Schema::table('media', function (Blueprint $table) {
            $table->string('status')->default('');
        });

        // Update the 'status' column of all rows to 'OK'
        DB::table('media')->update(['status' => 'OK']);
    }
};
