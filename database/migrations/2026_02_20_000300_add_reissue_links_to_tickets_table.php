<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'reissued_to_ticket_id')) {
                $table->unsignedBigInteger('reissued_to_ticket_id')->nullable()->index()->after('invoice_line_id');
            }
            if (! Schema::hasColumn('tickets', 'reissued_from_ticket_id')) {
                $table->unsignedBigInteger('reissued_from_ticket_id')->nullable()->index()->after('reissued_to_ticket_id');
            }
        });

        $addToForeign = Schema::hasColumn('tickets', 'reissued_to_ticket_id')
            && ! $this->hasForeignKey('tickets', 'tickets_reissued_to_ticket_id_foreign');
        $addFromForeign = Schema::hasColumn('tickets', 'reissued_from_ticket_id')
            && ! $this->hasForeignKey('tickets', 'tickets_reissued_from_ticket_id_foreign');

        Schema::table('tickets', function (Blueprint $table) use ($addToForeign, $addFromForeign): void {
            if ($addToForeign) {
                $table->foreign('reissued_to_ticket_id')->references('id')->on('tickets')->nullOnDelete();
            }
            if ($addFromForeign) {
                $table->foreign('reissued_from_ticket_id')->references('id')->on('tickets')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        $dropToForeign = $this->hasForeignKey('tickets', 'tickets_reissued_to_ticket_id_foreign');
        $dropFromForeign = $this->hasForeignKey('tickets', 'tickets_reissued_from_ticket_id_foreign');

        Schema::table('tickets', function (Blueprint $table) use ($dropToForeign, $dropFromForeign): void {
            if ($dropToForeign) {
                $table->dropForeign(['reissued_to_ticket_id']);
            }
            if ($dropFromForeign) {
                $table->dropForeign(['reissued_from_ticket_id']);
            }

            $dropColumns = [];
            if (Schema::hasColumn('tickets', 'reissued_to_ticket_id')) {
                $dropColumns[] = 'reissued_to_ticket_id';
            }
            if (Schema::hasColumn('tickets', 'reissued_from_ticket_id')) {
                $dropColumns[] = 'reissued_from_ticket_id';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
