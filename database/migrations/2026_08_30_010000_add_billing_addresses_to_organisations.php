<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('billing_address')->nullable()->after('parent_id');
            $table->string('billing_address2')->nullable()->after('billing_address');
            $table->string('billing_city', 120)->nullable()->after('billing_address2');
            $table->string('billing_state', 120)->nullable()->after('billing_city');
            $table->string('billing_postcode', 40)->nullable()->after('billing_state');
            $table->string('billing_country', 120)->nullable()->after('billing_postcode');
            $table->string('shipping_address')->nullable()->after('billing_country');
            $table->string('shipping_address2')->nullable()->after('shipping_address');
            $table->string('shipping_city', 120)->nullable()->after('shipping_address2');
            $table->string('shipping_state', 120)->nullable()->after('shipping_city');
            $table->string('shipping_postcode', 40)->nullable()->after('shipping_state');
            $table->string('shipping_country', 120)->nullable()->after('shipping_postcode');
            $table->text('invoice_email_to')->nullable()->after('shipping_country');
            $table->text('invoice_email_cc')->nullable()->after('invoice_email_to');
            $table->string('invoice_email_subject')->nullable()->after('invoice_email_cc');
            $table->text('invoice_email_message')->nullable()->after('invoice_email_subject');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('use_organisation_billing_address')->default(false)->after('primary_organisation_id');
            $table->boolean('use_organisation_shipping_address')->default(false)->after('use_organisation_billing_address');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('email_template_set')->default(false)->after('scheduled_email_failure');
            $table->text('email_template_to')->nullable()->after('email_template_set');
            $table->text('email_template_cc')->nullable()->after('email_template_to');
            $table->string('email_template_subject')->nullable()->after('email_template_cc');
            $table->text('email_template_message')->nullable()->after('email_template_subject');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['email_template_set', 'email_template_to', 'email_template_cc', 'email_template_subject', 'email_template_message']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['use_organisation_billing_address', 'use_organisation_shipping_address']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_address',
                'billing_address2',
                'billing_city',
                'billing_state',
                'billing_postcode',
                'billing_country',
                'shipping_address',
                'shipping_address2',
                'shipping_city',
                'shipping_state',
                'shipping_postcode',
                'shipping_country',
                'invoice_email_to',
                'invoice_email_cc',
                'invoice_email_subject',
                'invoice_email_message',
            ]);
        });
    }
};
