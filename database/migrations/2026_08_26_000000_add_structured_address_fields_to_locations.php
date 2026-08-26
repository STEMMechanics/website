<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->string('suburb')->nullable()->after('address');
            $table->string('state', 40)->nullable()->after('suburb');
            $table->string('postcode', 12)->nullable()->after('state');
            $table->decimal('latitude', 10, 7)->nullable()->after('postcode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->index(['suburb', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropIndex(['suburb', 'state']);
            $table->dropColumn(['suburb', 'state', 'postcode', 'latitude', 'longitude']);
        });
    }
};
