<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('billing_company')->nullable()->after('billing_phone');
            $table->string('billing_address')->nullable()->after('billing_company');
            $table->string('billing_address2')->nullable()->after('billing_address');
            $table->string('billing_city')->nullable()->after('billing_address2');
            $table->string('billing_state', 120)->nullable()->after('billing_city');
            $table->string('billing_postcode', 40)->nullable()->after('billing_state');
            $table->string('billing_country', 120)->nullable()->after('billing_postcode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['billing_company', 'billing_address', 'billing_address2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country']);
        });
    }
};
