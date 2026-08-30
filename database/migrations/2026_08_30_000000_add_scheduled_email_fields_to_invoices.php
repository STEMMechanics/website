<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('scheduled_email')->default(false)->after('due_date');
            $table->dateTime('scheduled_review_sent_at')->nullable()->after('scheduled_email');
            $table->dateTime('scheduled_email_queued_at')->nullable()->after('scheduled_review_sent_at');
            $table->dateTime('scheduled_email_sent_at')->nullable()->after('scheduled_email_queued_at');
            $table->dateTime('scheduled_email_failed_at')->nullable()->after('scheduled_email_sent_at');
            $table->text('scheduled_email_failure')->nullable()->after('scheduled_email_failed_at');
            $table->index(['scheduled_email', 'status', 'issue_date'], 'invoices_scheduled_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_scheduled_email_idx');
            $table->dropColumn([
                'scheduled_email',
                'scheduled_review_sent_at',
                'scheduled_email_queued_at',
                'scheduled_email_sent_at',
                'scheduled_email_failed_at',
                'scheduled_email_failure',
            ]);
        });
    }
};
