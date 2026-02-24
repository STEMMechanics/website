<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'reference_code')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->string('reference_code', 6)->nullable()->after('id');
            });
        }

        $this->backfillReferenceCodes();
        $this->ensureUniqueIndex();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'reference_code')) {
            return;
        }

        $this->dropUniqueIndexIfExists();

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('reference_code');
        });
    }

    private function backfillReferenceCodes(): void
    {
        $missingIds = DB::table('tickets')
            ->whereNull('reference_code')
            ->orWhere('reference_code', '')
            ->orderBy('id')
            ->pluck('id');

        foreach ($missingIds as $ticketId) {
            DB::table('tickets')
                ->where('id', (int) $ticketId)
                ->update(['reference_code' => $this->generateUniqueReferenceCode()]);
        }
    }

    private function generateUniqueReferenceCode(): string
    {
        // Excludes ambiguous characters: 0, O, 1, I, L, B, 8.
        $alphabet = '2345679ACDEFGHJKMNPQRTUVWXYZ';
        $length = 6;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $exists = DB::table('tickets')->where('reference_code', $code)->exists();
            if (! $exists) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique ticket reference code for backfill.');
    }

    private function ensureUniqueIndex(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            // Best-effort only; current deployments use MySQL/MariaDB.
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'tickets')
            ->where('index_name', 'tickets_reference_code_unique')
            ->exists();

        if (! $indexExists) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unique('reference_code');
            });
        }
    }

    private function dropUniqueIndexIfExists(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'tickets')
            ->where('index_name', 'tickets_reference_code_unique')
            ->exists();

        if ($indexExists) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropUnique('tickets_reference_code_unique');
            });
        }
    }
};
