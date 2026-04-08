<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'pick_list_custom_items')) {
                $table->json('pick_list_custom_items')->nullable()->after('pick_list_checked_item_ids');
            }

            if (! Schema::hasColumn('workshops', 'pick_list_is_customized')) {
                $table->boolean('pick_list_is_customized')->default(false)->after('pick_list_custom_items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'pick_list_is_customized')) {
                $table->dropColumn('pick_list_is_customized');
            }

            if (Schema::hasColumn('workshops', 'pick_list_custom_items')) {
                $table->dropColumn('pick_list_custom_items');
            }
        });
    }
};
