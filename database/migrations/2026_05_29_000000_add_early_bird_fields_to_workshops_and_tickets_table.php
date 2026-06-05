<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'early_bird_price')) {
                $table->decimal('early_bird_price', 10, 2)->nullable()->after('price');
            }

            if (! Schema::hasColumn('workshops', 'early_bird_ends_at')) {
                $table->timestamp('early_bird_ends_at')->nullable()->after('early_bird_price');
            }

            if (! Schema::hasColumn('workshops', 'early_bird_ticket_limit')) {
                $table->unsignedInteger('early_bird_ticket_limit')->nullable()->after('early_bird_ends_at');
            }

            if (! Schema::hasColumn('workshops', 'early_bird_note')) {
                $table->string('early_bird_note', 255)->nullable()->after('early_bird_ticket_limit');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'is_early_bird')) {
                $table->boolean('is_early_bird')->default(false)->after('reissued_from_ticket_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'is_early_bird')) {
                $table->dropColumn('is_early_bird');
            }
        });

        Schema::table('workshops', function (Blueprint $table): void {
            foreach (['early_bird_note', 'early_bird_ticket_limit', 'early_bird_ends_at', 'early_bird_price'] as $column) {
                if (Schema::hasColumn('workshops', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
