<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'quote_id')) {
            return;
        }

        $hadForeignKey = $this->hasForeignKey('invoices', 'invoices_quote_id_foreign');

        if ($hadForeignKey) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropForeign('invoices_quote_id_foreign');
            });
        }

        if (Schema::hasIndex('invoices', 'invoices_quote_id_unique')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropUnique('invoices_quote_id_unique');
            });
        }

        if (! Schema::hasIndex('invoices', 'invoices_quote_id_index')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index('quote_id');
            });
        }

        if ($hadForeignKey && ! $this->hasForeignKey('invoices', 'invoices_quote_id_foreign')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'quote_id')) {
            return;
        }

        $hadForeignKey = $this->hasForeignKey('invoices', 'invoices_quote_id_foreign');

        if ($hadForeignKey) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropForeign('invoices_quote_id_foreign');
            });
        }

        if (Schema::hasIndex('invoices', 'invoices_quote_id_index')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_quote_id_index');
            });
        }

        if (! Schema::hasIndex('invoices', 'invoices_quote_id_unique')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->unique('quote_id');
            });
        }

        if ($hadForeignKey && ! $this->hasForeignKey('invoices', 'invoices_quote_id_foreign')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            });
        }
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
