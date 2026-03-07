<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workshops', 'ticket_group_slug')) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table): void {
            $table->string('ticket_group_slug', 80)->nullable()->after('max_tickets');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workshops', 'ticket_group_slug')) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('ticket_group_slug');
        });
    }
};
