<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_order_invoice', function (Blueprint $table): void {
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->primary(['store_order_id', 'invoice_id']);
        });

        DB::table('store_orders')->whereNotNull('invoice_id')->orderBy('id')->chunkById(500, function ($orders): void {
            DB::table('store_order_invoice')->insert($orders->map(fn ($order): array => [
                'store_order_id' => $order->id,
                'invoice_id' => $order->invoice_id,
                'attached_by' => null,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_invoice');
    }
};
