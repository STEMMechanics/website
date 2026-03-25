<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('status', 20)->default('open')->after('user_id');
            $table->boolean('acceptance_creates_order')->default(false)->after('status');
            $table->boolean('acceptance_emails_invoice')->default(false)->after('acceptance_creates_order');
            $table->timestamp('accepted_at')->nullable()->after('notes');
            $table->timestamp('cancelled_at')->nullable()->after('accepted_at');
        });

        DB::table('quotes')
            ->whereNull('status')
            ->update(['status' => 'open']);

        DB::table('quotes')
            ->where('context_type', 'store_manual_shipping')
            ->update([
                'acceptance_creates_order' => true,
                'acceptance_emails_invoice' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'acceptance_creates_order',
                'acceptance_emails_invoice',
                'accepted_at',
                'cancelled_at',
            ]);
        });
    }
};
