<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentNumberService
{
    public function previewInvoiceNumber(): string
    {
        return $this->previewNext('invoice_number', 'invoices', 'invoice_number', 8630);
    }

    public function nextInvoiceNumber(): string
    {
        return $this->next('invoice_number', 'invoices', 'invoice_number', 8630);
    }

    public function previewQuoteNumber(): string
    {
        return $this->previewNext('quote_number', 'quotes', 'quote_number', 21415);
    }

    public function nextQuoteNumber(): string
    {
        return $this->next('quote_number', 'quotes', 'quote_number', 21415);
    }

    public function previewTaxAdjustmentNumber(): string
    {
        return $this->previewNext('tax_adjustment_number', 'tax_adjustments', 'adjustment_number', 7100);
    }

    public function nextTaxAdjustmentNumber(): string
    {
        return $this->next('tax_adjustment_number', 'tax_adjustments', 'adjustment_number', 7100);
    }

    private function previewNext(string $sequenceKey, string $table, string $column, int $minimum): string
    {
        $sequenceLast = (int) (DB::table('document_sequences')
            ->where('sequence_key', $sequenceKey)
            ->value('last_number') ?? 0);

        $currentMax = (int) ($this->currentNumericMax($table, $column) ?? 0);
        $next = max($minimum, $sequenceLast + 1, $currentMax + 1);

        return (string) $next;
    }

    private function next(string $sequenceKey, string $table, string $column, int $minimum): string
    {
        return DB::transaction(function () use ($sequenceKey, $table, $column, $minimum): string {
            $sequence = DB::table('document_sequences')
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    DB::table('document_sequences')->insert([
                        'sequence_key' => $sequenceKey,
                        'last_number' => $minimum - 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // Sequence row may have been created concurrently; continue.
                }

                $sequence = DB::table('document_sequences')
                    ->where('sequence_key', $sequenceKey)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $sequence) {
                throw new RuntimeException('Unable to load document sequence row.');
            }

            $currentMax = (int) ($this->currentNumericMax($table, $column) ?? 0);
            $lastNumber = (int) ($sequence->last_number ?? 0);
            $next = max($minimum, $lastNumber + 1, $currentMax + 1);

            DB::table('document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'last_number' => $next,
                    'updated_at' => now(),
                ]);

            return (string) $next;
        }, 3);
    }

    private function currentNumericMax(string $table, string $column): ?int
    {
        return DB::table($table)
            ->whereRaw("{$column} REGEXP '^[0-9]+$'")
            ->selectRaw("MAX(CAST({$column} AS UNSIGNED)) as max_number")
            ->value('max_number');
    }

}
