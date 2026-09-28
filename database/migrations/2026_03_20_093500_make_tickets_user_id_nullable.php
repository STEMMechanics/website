<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->supportsMySqlSchemaChanges() || ! Schema::hasColumn('tickets', 'user_id')) {
            return;
        }

        if ($this->hasForeignKey('tickets', 'tickets_user_id_foreign')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropForeign('tickets_user_id_foreign');
            });
        }

        Schema::table('tickets', function (Blueprint $table): void {
            // Laravel uses MariaDB's native UUID type on MariaDB 10.7+ and
            // CHAR(36) on older MariaDB versions and MySQL.
            $table->foreignUuid('user_id')->nullable()->change();
        });

        if (! $this->hasForeignKey('tickets', 'tickets_user_id_foreign')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! $this->supportsMySqlSchemaChanges() || ! Schema::hasColumn('tickets', 'user_id')) {
            return;
        }

        if (DB::table('tickets')->whereNull('user_id')->exists()) {
            return;
        }

        if ($this->hasForeignKey('tickets', 'tickets_user_id_foreign')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropForeign('tickets_user_id_foreign');
            });
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->nullable(false)->change();
        });

        if (! $this->hasForeignKey('tickets', 'tickets_user_id_foreign')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users');
            });
        }
    }

    private function supportsMySqlSchemaChanges(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
