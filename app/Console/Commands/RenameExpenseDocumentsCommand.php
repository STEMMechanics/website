<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RenameExpenseDocumentsCommand extends Command
{
    protected $signature = 'expenses:rename-documents {--dry-run : Show the changes without renaming any files}';

    protected $description = 'Rename stored expense documents to the current YYMMDD-SUPPLIER-EXP<ID>-INV<ID>.ext convention';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $renamed = 0;
        $skipped = 0;
        $missing = 0;

        Expense::query()
            ->whereNotNull('receipt_document_path')
            ->orderBy('id')
            ->chunkById(200, function ($expenses) use ($dryRun, &$renamed, &$skipped, &$missing): void {
                foreach ($expenses as $expense) {
                    $path = trim((string) ($expense->receipt_document_path ?? ''));
                    if ($path === '') {
                        $skipped++;
                        continue;
                    }

                    if (! Storage::disk('local')->exists($path)) {
                        $missing++;
                        $this->warn('Missing: Expense #'.$expense->id.' -> '.$path);
                        continue;
                    }

                    $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));
                    $targetPath = $this->resolveDocumentPath($expense, $extension, $path);

                    if ($targetPath === $path) {
                        $skipped++;
                        continue;
                    }

                    $this->line(($dryRun ? '[dry-run] ' : '').'Expense #'.$expense->id.': '.$path.' -> '.$targetPath);

                    if ($dryRun) {
                        $renamed++;
                        continue;
                    }

                    Storage::disk('local')->move($path, $targetPath);
                    $expense->receipt_document_path = $targetPath;
                    $expense->receipt_document_name = basename($targetPath);
                    $expense->save();
                    $renamed++;
                }
            }, 'id');

        $this->newLine();
        $this->info('Expense document rename complete.');
        $this->line('Renamed: '.$renamed);
        $this->line('Skipped (already correct/empty): '.$skipped);
        $this->line('Missing source files: '.$missing);

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDocumentPath(Expense $expense, string $extension, ?string $currentPath = null): string
    {
        $suffix = 0;

        do {
            $filename = $this->buildDocumentFilename($expense, $extension, $suffix);
            $path = 'finance/expenses/'.$filename;

            if ($path === $currentPath || ! Storage::disk('local')->exists($path)) {
                return $path;
            }

            $suffix++;
        } while (true);
    }

    private function buildDocumentFilename(Expense $expense, string $extension, int $suffix = 0): string
    {
        $datePart = ($expense->paid_on ?? now())->format('ymd');
        $supplier = $this->normalizeFilenamePart((string) ($expense->supplier ?? 'supplier'));
        $expenseIdPart = 'EXP'.((int) $expense->id);
        $invoiceId = trim((string) ($expense->invoice_id ?? ''));
        $invoicePart = $invoiceId !== '' ? '-INV'.$this->normalizeFilenamePart($invoiceId) : '';
        $normalizedExtension = trim($extension) !== '' ? strtolower(trim($extension)) : 'bin';
        $suffixPart = $suffix > 0 ? '-'.$suffix : '';

        return $datePart.'-'.$supplier.'-'.$expenseIdPart.$invoicePart.$suffixPart.'.'.$normalizedExtension;
    }

    private function normalizeFilenamePart(string $value): string
    {
        $normalized = Str::upper(Str::ascii(trim($value)));
        $normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim((string) $normalized, '-');

        return $normalized !== '' ? $normalized : 'NA';
    }
}
