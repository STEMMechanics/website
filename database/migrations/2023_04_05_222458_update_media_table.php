<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        DB::table('media')->whereNull('mime')->update(['mime' => '']);
        DB::table('media')->whereNull('permission')->update(['permission' => '']);

        Schema::table('media', function (Blueprint $table) {
            $table->string('storage')->default("cdn");
            $table->string('description')->default("");
            $table->string('status')->default("");
            $table->string('dimensions')->default("");
            $table->text('variants');

            $table->bigInteger('size')->default(0)->change();
            $table->string('permission')->default("")->nullable(false)->change();

            $table->string('mime')->default("")->nullable(false)->change();            
        });

        Schema::table('media', function(Blueprint $table) {
            $table->renameColumn('mime', 'mime_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->bigInteger('size')->change();
            $table->string('mime_type')->nullable(true)->change();
            $table->string('permission')->nullable(true)->change();

            $table->renameColumn('mime_type', 'mime');

            $table->dropColumn('status');
            $table->dropColumn('dimensions');
            $table->dropColumn('variants');
            $table->dropColumn('description');
            $table->dropColumn('storage');
        });
    }
};
