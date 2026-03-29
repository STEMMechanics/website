<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'class_session_id')) {
                $table->foreignUuid('class_session_id')->nullable()->after('registration_data')->constrained('class_sessions')->nullOnDelete();
            }

            if (! Schema::hasColumn('workshops', 'classroom_forum_category_id')) {
                $table->foreignUuid('classroom_forum_category_id')->nullable()->after('class_session_id')->constrained('forum_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('workshops', 'classroom_sessions_json')) {
                $table->text('classroom_sessions_json')->nullable()->after('classroom_forum_category_id');
            }
        });

        Schema::table('class_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_sessions', 'workshop_id')) {
                $table->foreignUuid('workshop_id')->nullable()->after('duplicated_from_class_session_id')->constrained('workshops')->nullOnDelete();
            }

            if (! Schema::hasColumn('class_sessions', 'term_number')) {
                $table->unsignedTinyInteger('term_number')->nullable()->after('title');
            }

            if (! Schema::hasColumn('class_sessions', 'broadcast_sessions_json')) {
                $table->text('broadcast_sessions_json')->nullable()->after('ends_at');
            }
        });

        if (Schema::hasColumn('workshops', 'classroom_sessions_json') && Schema::hasColumn('class_sessions', 'broadcast_sessions_json')) {
            DB::table('workshops')
                ->select(['class_session_id', 'classroom_sessions_json'])
                ->whereNotNull('class_session_id')
                ->whereNotNull('classroom_sessions_json')
                ->where('classroom_sessions_json', '!=', '')
                ->get()
                ->each(function ($workshop): void {
                    DB::table('class_sessions')
                        ->where('id', $workshop->class_session_id)
                        ->update([
                            'broadcast_sessions_json' => $workshop->classroom_sessions_json,
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('class_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('class_sessions', 'broadcast_sessions_json')) {
                $table->dropColumn('broadcast_sessions_json');
            }

            if (Schema::hasColumn('class_sessions', 'term_number')) {
                $table->dropColumn('term_number');
            }

            if (Schema::hasColumn('class_sessions', 'workshop_id')) {
                $table->dropConstrainedForeignId('workshop_id');
            }
        });

        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'classroom_sessions_json')) {
                $table->dropColumn('classroom_sessions_json');
            }

            if (Schema::hasColumn('workshops', 'classroom_forum_category_id')) {
                $table->dropConstrainedForeignId('classroom_forum_category_id');
            }

            if (Schema::hasColumn('workshops', 'class_session_id')) {
                $table->dropConstrainedForeignId('class_session_id');
            }
        });
    }
};
