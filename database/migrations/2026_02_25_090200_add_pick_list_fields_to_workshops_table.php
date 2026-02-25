<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'pick_list_template_id')) {
                $table->foreignId('pick_list_template_id')
                    ->nullable()
                    ->after('max_tickets')
                    ->constrained('pick_list_templates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('workshops', 'pick_list_participants')) {
                $table->unsignedInteger('pick_list_participants')
                    ->nullable()
                    ->after('pick_list_template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'pick_list_template_id')) {
                $table->dropConstrainedForeignId('pick_list_template_id');
            }

            if (Schema::hasColumn('workshops', 'pick_list_participants')) {
                $table->dropColumn('pick_list_participants');
            }
        });
    }
};
