<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_emails', function (Blueprint $table): void {
            if (! Schema::hasColumn('sent_emails', 'scheduled_for_at')) {
                $table->timestamp('scheduled_for_at')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('sent_emails', 'scheduled_for_at')) {
            DB::table('sent_emails')
                ->where('mailable_class', 'App\\Jobs\\SendDeferredStoreOrderEmail')
                ->whereNull('scheduled_for_at')
                ->update([
                    'scheduled_for_at' => DB::raw('created_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('sent_emails', function (Blueprint $table): void {
            if (Schema::hasColumn('sent_emails', 'scheduled_for_at')) {
                $table->dropColumn('scheduled_for_at');
            }
        });
    }
};
