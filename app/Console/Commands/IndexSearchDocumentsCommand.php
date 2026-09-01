<?php

namespace App\Console\Commands;

use App\Jobs\IndexSearchableDocument;
use App\Models\Expense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class IndexSearchDocumentsCommand extends Command
{
    protected $signature = 'search:index-documents
        {--all : Re-index documents that already have extracted text}';

    protected $description = 'Queue searchable-text extraction for stored documents';

    public function handle(): int
    {
        $query = Expense::query()
            ->whereNotNull('receipt_document_path')
            ->where('receipt_document_path', '!=', '')
            ->whereNull('receipt_document_index_queued_at');

        if (! $this->option('all')) {
            $query->whereNull('receipt_document_indexed_at')
                ->where(function ($builder): void {
                    $builder->whereNull('receipt_document_text')
                        ->orWhere('receipt_document_text', '');
                });
        }

        $queued = 0;

        $query->orderBy('id')->chunkById(100, function ($expenses) use (&$queued): void {
            foreach ($expenses as $expense) {
                $claimed = DB::transaction(fn (): bool => Expense::query()
                    ->whereKey($expense->id)
                    ->whereNull('receipt_document_index_queued_at')
                    ->update(['receipt_document_index_queued_at' => now()]) === 1);

                if ($claimed) {
                    try {
                        IndexSearchableDocument::dispatch(IndexSearchableDocument::TYPE_EXPENSE, (int) $expense->id);
                        $queued++;
                    } catch (Throwable $exception) {
                        report($exception);
                        Expense::query()->whereKey($expense->id)->update([
                            'receipt_document_index_queued_at' => null,
                        ]);
                    }
                }
            }
        });

        $this->info("Queued {$queued} document(s) for search indexing.");

        return self::SUCCESS;
    }
}
