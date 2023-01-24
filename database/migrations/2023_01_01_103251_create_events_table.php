<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('location');
            $table->string('address')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->timestamp('publish_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('registration_type');
            $table->string('registration_data')->nullable();
            $table->uuid('hero');
            $table->text('content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
};
