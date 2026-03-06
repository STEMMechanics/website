<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('minecraft_penalties', 'by_user_id')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->foreignUuid('by_user_id')->nullable()->after('by_uuid')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('minecraft_penalties', 'lifted_by_user_id')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->foreignUuid('lifted_by_user_id')->nullable()->after('lifted_by_uuid')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('minecraft_penalties', 'lift_reason')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->text('lift_reason')->nullable()->after('lifted_by_username');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('minecraft_penalties', 'by_user_id')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->dropForeign(['by_user_id']);
                $table->dropColumn('by_user_id');
            });
        }

        if (Schema::hasColumn('minecraft_penalties', 'lifted_by_user_id')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->dropForeign(['lifted_by_user_id']);
                $table->dropColumn('lifted_by_user_id');
            });
        }

        if (Schema::hasColumn('minecraft_penalties', 'lift_reason')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->dropColumn('lift_reason');
            });
        }
    }
};
