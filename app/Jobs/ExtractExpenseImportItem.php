<?php

namespace App\Jobs;

use App\Http\Controllers\AdminAiController;
use App\Services\ExpenseImportBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class ExtractExpenseImportItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [10];

    public int $timeout;

    public function __construct(
        public string $batchId,
        public string $itemId,
        public string $userId,
    ) {
        $this->timeout = max(240, (int) config('services.openai.timeout', 180) + 30);
    }

    public function handle(ExpenseImportBatchService $imports, AdminAiController $ai): void
    {
        $entry = $imports->claimAi($this->batchId, $this->itemId, $this->userId);
        if ($entry === null) {
            return;
        }

        try {
            $item = $entry['item'];
            $path = $imports->stagedFilePath($item);
            if (! is_file($path)) {
                throw new RuntimeException('The staged receipt is no longer available.');
            }

            $file = new UploadedFile(
                $path,
                basename((string) ($item['name'] ?? 'receipt')),
                (string) ($item['mime_type'] ?? 'application/octet-stream'),
                UPLOAD_ERR_OK,
                true,
            );

            $result = $ai->extractExpenseFile($file, [], true, true);
            $imports->markAiCompleted($this->batchId, $this->itemId, $this->userId, $result);
        } catch (Throwable $exception) {
            $imports->markAiQueued($this->batchId, $this->itemId, $this->userId);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ExpenseImportBatchService::class)->markAiFailed(
            $this->batchId,
            $this->itemId,
            $this->userId,
            $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The AI could not finish reading this receipt. You can complete the expense manually.',
        );
    }
}
