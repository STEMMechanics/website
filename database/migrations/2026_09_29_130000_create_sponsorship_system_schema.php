<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description');
            $table->string('logo_path')->nullable();
            $table->string('project_url')->nullable();
            $table->string('github_url')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('sponsorship_enabled')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->string('currency', 3)->default('AUD');
            $table->boolean('allow_custom_amount')->default(true);
            $table->decimal('custom_amount_min', 10, 2)->default(1);
            $table->decimal('custom_amount_max', 10, 2)->default(10000);
            $table->timestamps();
            $table->index(['enabled', 'sponsorship_enabled']);
        });

        Schema::create('sponsorship_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('sponsorship_projects')->cascadeOnDelete();
            $table->string('label', 100);
            $table->text('additional_benefits')->nullable();
            $table->string('checkout_group', 20)->default('both');
            $table->enum('frequency', ['one_time', 'monthly']);
            $table->decimal('amount', 10, 2);
            $table->boolean('recognition_enabled')->default(false);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'frequency', 'enabled', 'sort_order'], 'sponsorship_options_public_idx');
        });

        Schema::create('sponsorship_recognition_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('sponsorship_projects')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('minimum_total', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['project_id', 'enabled', 'minimum_total'], 'sponsorship_recognition_levels_lookup_idx');
        });

        Schema::create('sponsors', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('contact_name');
            $table->enum('sponsor_type', ['individual', 'organisation'])->default('individual');
            $table->string('company_name')->nullable();
            $table->string('abn', 20)->nullable();
            $table->string('foreign_tax_id', 100)->nullable();
            $table->string('country', 120);
            $table->boolean('non_resident_declaration')->default(false);
            $table->string('billing_address')->nullable();
            $table->string('billing_address2')->nullable();
            $table->string('billing_city', 120)->nullable();
            $table->string('billing_state', 120)->nullable();
            $table->string('billing_postcode', 40)->nullable();
            $table->string('square_customer_id')->nullable()->index();
            $table->string('square_card_id')->nullable();
            $table->boolean('recognition_public')->default(false);
            $table->string('display_name')->nullable();
            $table->string('recognition_company_name')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('recognition_logo_path')->nullable();
            $table->string('public_message', 500)->nullable();
            $table->timestamps();
            $table->index(['recognition_public', 'sponsor_type']);
            $table->index(['organisation_id', 'sponsor_type'], 'sponsors_organisation_type_idx');
        });

        Schema::create('sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('sponsors')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('sponsorship_projects')->restrictOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('sponsorship_options')->nullOnDelete();
            $table->string('checkout_type', 20)->default('business');
            $table->boolean('invoice_recipient_customized')->default(false);
            $table->enum('frequency', ['one_time', 'monthly']);
            $table->string('billing_method', 20)->default('square');
            $table->boolean('local_recurring_billing')->default(false);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('AUD');
            $table->enum('status', ['pending', 'active', 'past_due', 'cancelled', 'failed', 'completed'])->default('pending')->index();
            $table->string('referral_source', 120)->nullable()->index();
            $table->date('next_payment_date')->nullable();
            $table->date('billing_anchor_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['sponsor_id', 'status']);
            $table->index(['local_recurring_billing', 'frequency', 'status', 'next_payment_date'], 'sponsorships_local_billing_due_index');
            $table->index(['billing_method', 'frequency', 'status', 'next_payment_date'], 'sponsorships_billing_method_due_index');
        });

        Schema::create('sponsorship_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained('sponsorships')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('square_payment_id')->nullable()->unique();
            $table->string('square_order_id')->nullable()->index();
            $table->string('square_invoice_id')->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending')->index();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->string('tax_treatment', 40)->default('no_gst');
            $table->string('tax_code', 80)->nullable();
            $table->string('sponsor_country', 120);
            $table->date('billing_period')->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('square_idempotency_key', 45)->nullable()->unique();
            $table->string('invoice_number')->nullable()->index();
            $table->string('invoice_pdf_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->boolean('receipt_email_enabled')->default(true);
            $table->timestamps();
            $table->unique(
                ['sponsorship_id', 'billing_period', 'attempt_number'],
                'sponsorship_payments_billing_attempt_unique'
            );
            $table->index(['sponsorship_id', 'billing_period', 'status'], 'sponsorship_payments_billing_status_index');
            $table->index(['sponsorship_id', 'status', 'paid_at']);
        });

        Schema::create('sponsorship_magic_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_sponsor_supports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_id')->constrained('sponsors')->restrictOnDelete();
            $table->foreignId('sponsorship_id')->nullable()->constrained('sponsorships')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('sponsorship_projects')->nullOnDelete();
            $table->foreignId('recognition_level_id')->nullable()->constrained('sponsorship_recognition_levels')->nullOnDelete();
            $table->enum('support_method', ['cheque', 'cash', 'bank_transfer', 'other_benefit']);
            $table->string('support_description', 500)->nullable();
            $table->decimal('value_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('AUD');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sponsor_id', 'starts_on', 'ends_on'], 'manual_sponsor_supports_period_idx');
        });

        Schema::create('sponsorship_invoice_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->char('token_hash', 64)->unique();
            $table->longText('payload');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('website_url', 2048)->nullable();
            $table->string('logo_path', 2048)->nullable();
            $table->string('abn', 20)->nullable();
            $table->string('foreign_tax_id', 100)->nullable();
            $table->boolean('sponsorship_recognition_public')->default(false);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('tax_treatment_code', 40)->nullable()->after('gst_amount');
            $table->string('recipient_abn', 20)->nullable()->after('billing_country');
            $table->string('recipient_foreign_tax_id', 100)->nullable()->after('recipient_abn');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('event', 120)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorship_invoice_requests');
        Schema::dropIfExists('manual_sponsor_supports');
        Schema::dropIfExists('sponsorship_magic_links');
        Schema::dropIfExists('sponsorship_payments');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('sponsorship_recognition_levels');
        Schema::dropIfExists('sponsorship_options');
        Schema::dropIfExists('sponsorship_projects');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['tax_treatment_code', 'recipient_abn', 'recipient_foreign_tax_id']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['website_url', 'logo_path', 'abn', 'foreign_tax_id', 'sponsorship_recognition_public']);
        });

        if (! DB::table('audit_logs')->whereRaw('CHAR_LENGTH(event) > 30')->exists()) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('event', 30)->change();
            });
        }
    }
};
