<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable()->after('invoice_id')->constrained('quotes')->nullOnDelete();
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = DB::table('store_orders')
                ->select('store_orders.id', 'invoices.quote_id')
                ->join('invoices', 'invoices.id', '=', 'store_orders.invoice_id')
                ->whereNull('store_orders.quote_id')
                ->whereNotNull('invoices.quote_id')
                ->orderBy('store_orders.id')
                ->get();

            foreach ($rows as $row) {
                DB::table('store_orders')
                    ->where('id', $row->id)
                    ->update(['quote_id' => $row->quote_id]);
            }

            return;
        }

        DB::statement('UPDATE store_orders INNER JOIN invoices ON invoices.id = store_orders.invoice_id SET store_orders.quote_id = invoices.quote_id WHERE store_orders.quote_id IS NULL AND invoices.quote_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
