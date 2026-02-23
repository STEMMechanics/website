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
        Schema::table('sent_emails', function (Blueprint $table): void {
            if (! Schema::hasColumn('sent_emails', 'status')) {
                $table->string('status', 20)->default('queued')->after('mailable_class');
            }
            if (! Schema::hasColumn('sent_emails', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('sent_emails', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('sent_emails', 'error_message')) {
                $table->text('error_message')->nullable()->after('failed_at');
            }
        });

        if (Schema::hasColumn('sent_emails', 'status') && Schema::hasColumn('sent_emails', 'sent_at')) {
            DB::table('sent_emails')
                ->whereNull('sent_at')
                ->update([
                    'status' => 'sent',
                    'sent_at' => DB::raw('created_at'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sent_emails', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['status', 'sent_at', 'failed_at', 'error_message'] as $column) {
                if (Schema::hasColumn('sent_emails', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
