<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $legacySquareColumns = [
        'square_payment_id',
        'square_order_id',
        'square_location_id',
        'square_receipt_url',
        'square_card_brand',
        'square_card_last4',
        'square_paid_money_amount',
        'square_refunded_money_amount',
        'square_gateway_created_at',
        'square_gateway_updated_at',
        'square_last_event_type',
        'square_last_event_id',
        'square_last_event_at',
        'square_webhook_payload',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'square_integration_meta')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->json('square_integration_meta')->nullable()->after('gateway_reference_id');
            });
        }

        $presentLegacyColumns = array_values(array_filter(
            $this->legacySquareColumns,
            static fn (string $column): bool => Schema::hasColumn('payments', $column)
        ));

        if ($presentLegacyColumns !== []) {
            $select = array_merge(['id', 'square_integration_meta'], $presentLegacyColumns);

            DB::table('payments')
                ->select($select)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($presentLegacyColumns): void {
                    foreach ($rows as $row) {
                        $meta = $this->decodeMeta($row->square_integration_meta ?? null);

                        foreach ($presentLegacyColumns as $column) {
                            $normalized = $this->normalizeLegacyValueForMeta($column, $row->{$column} ?? null);
                            if ($normalized === null) {
                                continue;
                            }

                            $meta[$column] = $normalized;
                        }

                        DB::table('payments')
                            ->where('id', (int) $row->id)
                            ->update([
                                'square_integration_meta' => $meta === []
                                    ? null
                                    : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ]);
                    }
                });

            Schema::table('payments', function (Blueprint $table) use ($presentLegacyColumns): void {
                $table->dropColumn($presentLegacyColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'square_payment_id')) {
                $table->string('square_payment_id', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('payments', 'square_order_id')) {
                $table->string('square_order_id', 120)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_location_id')) {
                $table->string('square_location_id', 120)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_receipt_url')) {
                $table->text('square_receipt_url')->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_card_brand')) {
                $table->string('square_card_brand', 40)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_card_last4')) {
                $table->string('square_card_last4', 4)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_paid_money_amount')) {
                $table->unsignedBigInteger('square_paid_money_amount')->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_refunded_money_amount')) {
                $table->unsignedBigInteger('square_refunded_money_amount')->default(0);
            }
            if (! Schema::hasColumn('payments', 'square_gateway_created_at')) {
                $table->timestamp('square_gateway_created_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_gateway_updated_at')) {
                $table->timestamp('square_gateway_updated_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_last_event_type')) {
                $table->string('square_last_event_type', 120)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_last_event_id')) {
                $table->string('square_last_event_id', 120)->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_last_event_at')) {
                $table->timestamp('square_last_event_at')->nullable();
            }
            if (! Schema::hasColumn('payments', 'square_webhook_payload')) {
                $table->json('square_webhook_payload')->nullable();
            }
        });

        if (! Schema::hasColumn('payments', 'square_integration_meta')) {
            return;
        }

        DB::table('payments')
            ->select(['id', 'square_integration_meta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = $this->decodeMeta($row->square_integration_meta ?? null);

                    $updates = [
                        'square_payment_id' => $this->stringOrNull($meta['square_payment_id'] ?? null),
                        'square_order_id' => $this->stringOrNull($meta['square_order_id'] ?? null),
                        'square_location_id' => $this->stringOrNull($meta['square_location_id'] ?? null),
                        'square_receipt_url' => $this->stringOrNull($meta['square_receipt_url'] ?? null),
                        'square_card_brand' => $this->stringOrNull($meta['square_card_brand'] ?? null),
                        'square_card_last4' => $this->stringOrNull($meta['square_card_last4'] ?? null),
                        'square_paid_money_amount' => $this->intOrNull($meta['square_paid_money_amount'] ?? null),
                        'square_refunded_money_amount' => max(0, (int) ($meta['square_refunded_money_amount'] ?? 0)),
                        'square_gateway_created_at' => $this->stringOrNull($meta['square_gateway_created_at'] ?? null),
                        'square_gateway_updated_at' => $this->stringOrNull($meta['square_gateway_updated_at'] ?? null),
                        'square_last_event_type' => $this->stringOrNull($meta['square_last_event_type'] ?? null),
                        'square_last_event_id' => $this->stringOrNull($meta['square_last_event_id'] ?? null),
                        'square_last_event_at' => $this->stringOrNull($meta['square_last_event_at'] ?? null),
                        'square_webhook_payload' => $this->arrayOrNull($meta['square_webhook_payload'] ?? null),
                    ];

                    DB::table('payments')
                        ->where('id', (int) $row->id)
                        ->update($updates);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeLegacyValueForMeta(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array($column, ['square_paid_money_amount', 'square_refunded_money_amount'], true)) {
            return (int) $value;
        }

        if ($column === 'square_webhook_payload') {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : null;
            }

            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }
};
