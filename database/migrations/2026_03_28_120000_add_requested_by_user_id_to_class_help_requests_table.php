<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('class_help_requests', 'requested_by_user_id')) {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->foreignUuid('requested_by_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasIndex('class_help_requests', 'chreq_sess_req_status_idx')) {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->index(['class_session_id', 'requested_by_user_id', 'status'], 'chreq_sess_req_status_idx');
            });
        }

        DB::table('class_help_requests')
            ->whereNull('requested_by_user_id')
            ->update([
                'requested_by_user_id' => DB::raw('user_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('class_help_requests', function (Blueprint $table): void {
            if ($this->hasIndex('class_help_requests', 'chreq_sess_req_status_idx')) {
                $table->dropIndex('chreq_sess_req_status_idx');
            }

            if (Schema::hasColumn('class_help_requests', 'requested_by_user_id')) {
                $table->dropConstrainedForeignId('requested_by_user_id');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM `'.$table.'`');
            foreach ($indexes as $row) {
                if (($row->Key_name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ?', [$table]);
            foreach ($rows as $row) {
                if (($row->indexname ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        return Schema::hasTable($table);
    }
};
