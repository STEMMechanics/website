<?php

namespace App\Jobs;

use App\Models\Expense;
use App\Services\PdfTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class IndexSearchableDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TYPE_EXPENSE = 'expense';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public string $documentType,
        public int $documentId,
    ) {}

    public function handle(PdfTextExtractor $extractor): void
    {
        if ($this->documentType !== self::TYPE_EXPENSE) {
            return;
        }

        $expense = Expense::query()->find($this->documentId);
        if (! $expense || $expense->receipt_document_index_queued_at === null) {
            return;
        }

        $expense->forceFill([
            'receipt_document_text' => $extractor->extract($expense->receipt_document_path),
            'receipt_document_index_queued_at' => null,
            'receipt_document_indexed_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->documentType === self::TYPE_EXPENSE) {
            Expense::query()->whereKey($this->documentId)->update([
                'receipt_document_index_queued_at' => null,
            ]);
        }
    }
}
