<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('finance_owner_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->uuid('token')->unique();
            $table->date('date')->index();
            $table->bigInteger('cents');
            $table->string('reference');
            $table->json('splits');
            $table->timestamps();
        });
        Schema::table('finance_drawings', fn (Blueprint $table) => $table->string('purpose')->default('time'));
    }
    public function down(): void {
        Schema::table('finance_drawings', fn (Blueprint $table) => $table->dropColumn('purpose'));
        Schema::dropIfExists('finance_owner_contributions');
    }
};
