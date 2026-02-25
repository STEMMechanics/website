<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'pick_list_notes')) {
                $table->text('pick_list_notes')->nullable()->after('pick_list_checked_item_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'pick_list_notes')) {
                $table->dropColumn('pick_list_notes');
            }
        });
    }
};
