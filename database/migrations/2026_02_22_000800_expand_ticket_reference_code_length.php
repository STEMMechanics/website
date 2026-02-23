<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'reference_code')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE tickets MODIFY reference_code VARCHAR(6) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tickets ALTER COLUMN reference_code TYPE VARCHAR(6)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'reference_code')) {
            return;
        }

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE tickets MODIFY reference_code VARCHAR(5) NOT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tickets ALTER COLUMN reference_code TYPE VARCHAR(5)');
        }
    }
};
