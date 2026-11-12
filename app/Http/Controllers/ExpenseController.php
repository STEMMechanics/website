<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Jobs\ExtractExpenseImportItem;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Finance\ExpenseAllocation;
use App\Services\Finance\FinanceAttention;
use App\Services\Finance\FinancePlanner;
use App\Services\ExpenseDuplicateService;
use App\Services\ExpenseImportBatchService;
use App\Services\PdfTextExtractor;
use App\Services\ProductPurchaseService;
use App\Services\ProductRecipeService;
use App\Services\SiteListControls;
use App\Services\StoreInventoryAllocatorService;
use App\Support\ListPageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ExpenseController extends Controller
{
    public function __construct(
        private PdfTextExtractor $documentTextExtractor,
        private ExpenseDuplicateService $expenseDuplicates,
        private ExpenseImportBatchService $expenseImports,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate(['supplier_id' => 'nullable|integer|exists:finance_supplier_rules,id']);

        return $this->listing($request, isset($data['supplier_id']) ? Supplier::findOrFail($data['supplier_id']) : null);
    }

    public function supplier(Request $request, Supplier $supplier)
    {
        return $this->listing($request, $supplier);
    }

    private function listing(Request $request, ?Supplier $supplier = null)
    {
        $query = Expense::query()->with('creator');
        if ($supplier !== null) {
            $query->where('supplier_id', $supplier->id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $like = '%'.$search.'%';

                    $builder->where('supplier', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('invoice_id', 'like', '%'.$search.'%')
                        ->orWhereRaw($this->decimalSearchExpression('total_amount').' like ?', [$like])
                        ->orWhereRaw($this->decimalSearchExpression('gst_amount').' like ?', [$like]);
                });
            }
        }

        $this->applyAdvancedSearch($query, $request);

        if ($request->boolean('no_attachment')) {
            $query->where(function ($builder): void {
                $builder->whereNull('receipt_document_path')
                    ->orWhere('receipt_document_path', '');
            });
        }

        $expenses = $query->orderBy('paid_on', 'desc')->orderBy('created_at', 'desc')->tap(fn ($listingQuery) => app(SiteListControls::class)->apply($listingQuery))->paginate(ListPageSize::resolve(20))->onEachSide(1);
        $expenses->getCollection()->each(function (Expense $expense): void {
            $expense->setAttribute('receipt_document_exists', $expense->hasReceiptDocument());
        });

        $presetQuery = Expense::query();
        if ($request->routeIs('admin.supplier.show') && $supplier !== null) {
            $presetQuery->where('supplier_id', $supplier->id);
            $unallocatedQuery = clone $presetQuery;
            app(FinanceAttention::class)->unallocatedExpenses($unallocatedQuery);
            $unallocatedExpenseCount = $unallocatedQuery->count();
        } else {
            $unallocatedExpenseCount = (int) app(FinanceAttention::class)->counts()['expenses'];
        }
        $request->attributes->set('collection_preset_counts', [
            'All expenses' => $presetQuery->count(),
            'Needs allocation' => $unallocatedExpenseCount,
        ]);
        $request->attributes->set('collection_preset_attention', [
            'Needs allocation' => $unallocatedExpenseCount > 0,
        ]);

        return view('admin.expense.index', [
            'expenses' => $expenses,
            'selectedSupplier' => $supplier,
            'supplierCostCentre' => $supplier?->category_id ? DB::table('finance_categories')->where('id', $supplier->category_id)->value('name') : null,
        ]);
    }

    public function allocation(Request $request, FinancePlanner $planner, Expense $expense): RedirectResponse
    {
        DB::transaction(function () use ($request, $expense): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $expense->refresh();
            app(ExpenseAllocation::class)->save($request, $expense, true);
        });

        return redirect()->route('admin.expense.edit', $expense)->with('message', 'Expense allocation saved.')->with('message-type', 'success');
    }

    public function exportZip(Request $request): BinaryFileResponse
    {
        $expenses = $this->selectedExpenses($request);
        $exportName = 'expenses-'.now()->format('Ymd-His');
        $zipPath = tempnam(sys_get_temp_dir(), 'expense-export-');
        if (! is_string($zipPath)) {
            throw new RuntimeException('Unable to create the expense export archive.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('Unable to create the expense export archive.');
        }

        $zip->addFromString($exportName.'/expenses.csv', $this->expenseCsv($expenses));
        $zip->addEmptyDir($exportName.'/attachments');
        foreach ($expenses as $expense) {
            $path = trim((string) $expense->receipt_document_path);
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $name = basename(str_replace('\\', '/', trim((string) ($expense->receipt_document_name ?: basename($path)))));
            $name = $name !== '' ? $name : basename($path);
            $zip->addFile(Storage::disk('local')->path($path), $exportName.'/attachments/EXP'.$expense->id.'-'.$name);
        }
        $zip->close();

        return response()->download($zipPath, $exportName.'.zip')->deleteFileAfterSend(true);
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'supplier' => 'nullable|string|max:255',
            'import_batch' => ['nullable', 'uuid'],
            'import_item' => ['nullable', 'uuid'],
        ]);
        $expenseImport = null;
        if (filled($data['import_batch'] ?? null) || filled($data['import_item'] ?? null)) {
            if (! filled($data['import_batch'] ?? null) || ! filled($data['import_item'] ?? null)) {
                abort(404);
            }

            $expenseImport = $this->expenseImportContext((string) $data['import_batch'], (string) $data['import_item']);
        }

        return view('admin.expense.edit', [
            'supplierSuggestions' => $this->supplierSuggestions(),
            'supplierName' => $data['supplier'] ?? '',
            'purchaseCatalog' => app(ProductPurchaseService::class)->catalog(),
            'purchaseRows' => [],
            'expenseImport' => $expenseImport,
        ]);
    }

    public function startImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:12288'],
        ]);
        $batch = $this->expenseImports->create((string) Auth::id(), $validated['files']);
        foreach ($batch['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            ExtractExpenseImportItem::dispatch(
                $batch['id'],
                (string) $item['id'],
                (string) Auth::id(),
            );
        }
        $first = $batch['items'][0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('The receipt import did not contain any files.');
        }

        return response()->json([
            'success' => true,
            'batch_id' => $batch['id'],
            'count' => count($batch['items']),
            'redirect' => route('admin.expense.create', [
                'import_batch' => $batch['id'],
                'import_item' => $first['id'],
            ]),
        ]);
    }

    public function importFile(string $batchId, string $itemId)
    {
        $entry = $this->expenseImports->itemForUser($batchId, $itemId, (string) Auth::id());
        abort_if($entry === null, 404);

        $item = $entry['item'];
        $path = $this->expenseImports->stagedFilePath($item);
        $name = preg_replace('/[\r\n"]+/', '', basename((string) ($item['name'] ?? 'receipt'))) ?: 'receipt';

        return response()->file($path, [
            'Content-Type' => (string) ($item['mime_type'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function importAiStatus(string $batchId, string $itemId): JsonResponse
    {
        $entry = $this->expenseImports->aiItemForUser($batchId, $itemId, (string) Auth::id());
        abort_if($entry === null, 404);

        $item = $entry['item'];

        return response()->json([
            'status' => (string) ($item['ai_status'] ?? 'queued'),
            'result' => is_array($item['ai_result'] ?? null) ? $item['ai_result'] : null,
            'message' => filled($item['ai_message'] ?? null) ? (string) $item['ai_message'] : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function skipImport(string $batchId, string $itemId): JsonResponse
    {
        $entry = $this->expenseImports->itemForUser($batchId, $itemId, (string) Auth::id());
        abort_if($entry === null, 404);

        $next = $this->expenseImports->markSkipped($batchId, $itemId, (string) Auth::id());
        $redirect = $next === null
            ? route('admin.expense.index')
            : route('admin.expense.create', [
                'import_batch' => $batchId,
                'import_item' => $next['id'],
            ]);

        return response()->json([
            'success' => true,
            'skipped_item_id' => $itemId,
            'redirect' => $redirect,
        ]);
    }

    public function duplicateInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier' => ['nullable', 'string', 'max:255'],
            'invoice_id' => ['nullable', 'string', 'max:120'],
            'expense_id' => ['nullable', 'integer', 'exists:expenses,id'],
        ]);

        $matches = $this->expenseDuplicates->matches(
            $validated['supplier'] ?? null,
            $validated['invoice_id'] ?? null,
            isset($validated['expense_id']) ? (int) $validated['expense_id'] : null,
        );

        return response()->json([
            'matches' => $matches->map(fn (Expense $expense): array => $this->expenseDuplicates->payload($expense))->values()->all(),
        ]);
    }

    public function store(
        Request $request,
        ProductPurchaseService $purchases,
        StoreInventoryAllocatorService $stockAllocator,
    ) {
        $expenseImport = $this->prepareExpenseImport($request);
        $validated = $this->validateRequest($request);
        $this->ensureInvoiceIsNotDuplicate($validated['supplier'], $validated['invoice_id']);
        $purchaseRows = $purchases->validate($request);

        $expense = new Expense;
        $expense->fill($validated);
        $expense->created_by = Auth::id();
        $productIds = DB::transaction(function () use ($request, $expense, $purchases, $purchaseRows): array {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $supplier = Supplier::forName($expense->supplier);
            $expense->save();
            app(ExpenseAllocation::class)->save($request, $expense);
            if ($supplier->wasRecentlyCreated && $request->boolean('allocation_editor') && $request->boolean('allocation_override')) {
                $amounts = DB::table('finance_expense_splits')->where('expense_id', $expense->id)->pluck('cents', 'category_id');
                $total = (int) $amounts->sum();
                if ($total > 0) {
                    $supplier->update([
                        'mode' => 'split',
                        'category_id' => $amounts->count() === 1 ? $amounts->keys()->first() : null,
                        'splits' => $amounts->map(fn ($cents) => $cents / $total * 100)->all(),
                    ]);
                }
            }

            if ($purchaseRows !== null) {
                return $purchases->sync($expense, $purchaseRows);
            }
            if ($expense->productPurchases()->exists()) {
                app(ProductRecipeService::class)->refreshPrices();
            }

            return [];
        });

        $this->replaceDocument($expense, $request->file('receipt_document_file'));
        $this->renameDocumentToCurrentConvention($expense);
        $expense->save();
        $this->allocateProducts($productIds, $stockAllocator);

        $redirect = route('admin.expense.index');
        if ($expenseImport !== null) {
            $next = $this->expenseImports->markSaved(
                $expenseImport['batch_id'],
                $expenseImport['item_id'],
                (string) Auth::id(),
                (int) $expense->id,
            );
            if ($next !== null) {
                $redirect = route('admin.expense.create', [
                    'import_batch' => $expenseImport['batch_id'],
                    'import_item' => $next['id'],
                ]);
            }
        }

        session()->flash('message', 'Expense has been recorded');
        session()->flash('message-title', 'Expense recorded');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'expense_id' => $expense->id,
                'expense_url' => route('admin.expense.edit', $expense),
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect);
    }

    public function edit(Expense $expense)
    {
        $expense->load(['productPurchases.product', 'productPurchases.variant']);

        return view('admin.expense.edit', [
            'expense' => $expense,
            'supplierSuggestions' => $this->supplierSuggestions(),
            'purchaseCatalog' => app(ProductPurchaseService::class)->catalog($expense),
            'purchaseRows' => $expense->productPurchases->map(fn ($row): array => [
                'id' => (int) $row->id, 'product_id' => (int) $row->product_id,
                'product_variant_id' => $row->product_variant_id, 'item_name' => $row->product->title ?? '',
                'quantity' => $row->quantity, 'total_cost_ex_tax' => $row->total_cost_ex_tax,
                'add_inventory' => $row->inventory_added > 0,
                'individual_units' => (bool) $row->individual_units,
                'purchase_pack' => (bool) $row->purchase_pack,
                'units_per_purchase' => $row->units_per_purchase,
            ])->values()->all(),
        ]);
    }

    public function update(
        Request $request,
        Expense $expense,
        ProductPurchaseService $purchases,
        StoreInventoryAllocatorService $stockAllocator,
    ) {
        $validated = $this->validateRequest($request);
        $purchaseRows = $purchases->validate($request);

        $expense->fill($validated);
        $this->ensureInvoiceIsNotDuplicate($expense->supplier, $expense->invoice_id, $expense->id);
        $productIds = DB::transaction(function () use ($request, $expense, $purchases, $purchaseRows): array {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $expense->save();
            app(ExpenseAllocation::class)->save($request, $expense);

            if ($purchaseRows !== null) {
                return $purchases->sync($expense, $purchaseRows);
            }
            if ($expense->productPurchases()->exists()) {
                app(ProductRecipeService::class)->refreshPrices();
            }

            return [];
        });

        $this->replaceDocument($expense, $request->file('receipt_document_file'));
        $this->renameDocumentToCurrentConvention($expense);
        $expense->save();
        $this->allocateProducts($productIds, $stockAllocator);

        session()->flash('message', 'Expense has been updated');
        session()->flash('message-title', 'Expense updated');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.expense.edit', $expense),
            ]);
        }

        return redirect()->back();
    }

    public function destroy(Expense $expense)
    {
        DB::transaction(function () use ($expense): void {
            app(ProductPurchaseService::class)->sync($expense, []);
            $expense->delete();
            app(ProductRecipeService::class)->refreshPrices();
        });
        $this->deleteDocument($expense->receipt_document_path);

        session()->flash('message', 'Expense has been deleted');
        session()->flash('message-title', 'Expense deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.expense.index');
    }

    public function viewDocument(Expense $expense)
    {
        return $this->sendDocumentResponse($expense, false);
    }

    public function downloadDocument(Expense $expense)
    {
        return $this->sendDocumentResponse($expense, true);
    }

    public function removeDocument(Request $request, Expense $expense)
    {
        $this->deleteDocument($expense->receipt_document_path);
        $expense->receipt_document_path = null;
        $expense->receipt_document_name = null;
        $expense->receipt_document_text = null;
        $expense->receipt_document_index_queued_at = null;
        $expense->receipt_document_indexed_at = null;
        $expense->save();

        session()->flash('message', 'Expense attachment removed');
        session()->flash('message-title', 'Attachment removed');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('admin.expense.edit', $expense),
            ]);
        }

        return redirect()->route('admin.expense.edit', $expense);
    }

    private function validateRequest(Request $request): array
    {
        $maxSize = max((int) round(Helpers::getMaxUploadSize() / 1024), 1);

        return $request->validate([
            'supplier' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'invoice_id' => ['required', 'string', 'max:120'],
            'paid_on' => ['nullable', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['required', 'numeric', 'min:0', 'lte:total_amount'],
            'receipt_document_file' => ['nullable', 'file', 'max:'.$maxSize],
        ]);
    }

    /** @return array{batch_id: string, item_id: string}|null */
    private function prepareExpenseImport(Request $request): ?array
    {
        $batchId = trim((string) $request->input('expense_import_batch', ''));
        $itemId = trim((string) $request->input('expense_import_item', ''));
        if ($batchId === '' && $itemId === '') {
            return null;
        }
        if ($batchId === '' || $itemId === '') {
            abort(404);
        }

        $entry = $this->expenseImports->itemForUser($batchId, $itemId, (string) Auth::id());
        abort_if($entry === null, 404);
        $item = $entry['item'];
        if (! $request->hasFile('receipt_document_file')) {
            $path = $this->expenseImports->stagedFilePath($item);
            $request->files->set('receipt_document_file', new UploadedFile(
                $path,
                basename((string) ($item['name'] ?? 'receipt')),
                (string) ($item['mime_type'] ?? 'application/octet-stream'),
                UPLOAD_ERR_OK,
                true,
            ));
        }

        return ['batch_id' => $batchId, 'item_id' => $itemId];
    }

    /** @return array<string, mixed> */
    private function expenseImportContext(string $batchId, string $itemId): array
    {
        $entry = $this->expenseImports->itemForUser($batchId, $itemId, (string) Auth::id());
        abort_if($entry === null, 404);

        $batch = $entry['batch'];
        $item = $entry['item'];

        return [
            'batch_id' => $batchId,
            'item_id' => $itemId,
            'file_url' => route('admin.expense.import.file', [$batchId, $itemId]),
            'file_name' => (string) ($item['name'] ?? 'receipt'),
            'file_type' => (string) ($item['mime_type'] ?? 'application/octet-stream'),
            'position' => (int) ($item['position'] ?? 1),
            'total' => count($batch['items']),
            'is_last' => $this->expenseImports->nextPending($batch, $itemId) === null,
            'ai_status_url' => route('admin.expense.import.ai', [$batchId, $itemId]),
        ];
    }

    private function ensureInvoiceIsNotDuplicate(?string $supplier, ?string $invoiceId, ?int $ignoreExpenseId = null): void
    {
        $matches = $this->expenseDuplicates->matches($supplier, $invoiceId, $ignoreExpenseId);
        if ($matches->isEmpty()) {
            return;
        }

        $first = $matches->first();
        throw ValidationException::withMessages([
            'invoice_id' => 'Possible duplicate invoice ID. Review expense #'.$first->id.' before saving.',
        ]);
    }

    /** @param list<int> $productIds */
    private function allocateProducts(array $productIds, StoreInventoryAllocatorService $allocator): void
    {
        foreach (Product::query()->with('variants')->whereIn('id', $productIds)->get() as $product) {
            if (! $product->isPhysical()) {
                continue;
            }
            $allocator->allocateForProduct($product);
            foreach ($product->variants as $variant) {
                $allocator->allocateForVariant($variant);
            }
        }
    }

    private function applyAdvancedSearch(Builder $query, Request $request): void
    {
        foreach (['supplier', 'description', 'invoice_id'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '') {
                $query->where($field, 'like', '%'.$value.'%');
            }
        }

        $attachment = trim((string) $request->query('attachment', ''));
        if ($attachment !== '') {
            $query->where(function ($builder) use ($attachment): void {
                $builder->where('receipt_document_name', 'like', '%'.$attachment.'%')
                    ->orWhere('receipt_document_text', 'like', '%'.$attachment.'%');
            });
        }

        if ($request->filled('paid_from')) {
            $query->whereDate('paid_on', '>=', $request->query('paid_from'));
        }
        if ($request->filled('paid_to')) {
            $query->whereDate('paid_on', '<=', $request->query('paid_to'));
        }
    }

    private function selectedExpenses(Request $request): Collection
    {
        $validated = $request->validate([
            'expense_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'expense_ids.*' => ['required', 'integer', 'distinct', 'exists:expenses,id'],
        ]);
        $ids = collect($validated['expense_ids'])->map(fn ($id): int => (int) $id)->values();

        return Expense::query()->whereIn('id', $ids)->get()
            ->sortBy(fn (Expense $expense): int => $ids->search((int) $expense->id))
            ->values();
    }

    private function expenseCsv(Collection $expenses): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Date paid', 'Supplier', 'Invoice ID', 'Description', 'Amount incl GST', 'GST', 'Attachment']);
        foreach ($expenses as $expense) {
            fputcsv($stream, [
                $expense->paid_on?->format('Y-m-d') ?? '',
                $expense->supplier,
                $expense->invoice_id,
                $expense->description,
                number_format((float) $expense->total_amount, 2, '.', ''),
                number_format((float) $expense->gst_amount, 2, '.', ''),
                $expense->receipt_document_name,
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    private function replaceDocument(Expense $expense, ?UploadedFile $file): void
    {
        if ($file === null) {
            return;
        }

        $this->deleteDocument($expense->receipt_document_path);

        $path = $this->resolveDocumentPath($expense, $file->getClientOriginalExtension());
        $storedPath = $file->storeAs(dirname($path), basename($path), 'local');

        $expense->receipt_document_path = $storedPath;
        $expense->receipt_document_name = basename($storedPath);
        $expense->receipt_document_text = $this->documentTextExtractor->extract($storedPath);
        $expense->receipt_document_index_queued_at = null;
        $expense->receipt_document_indexed_at = now();
    }

    private function renameDocumentToCurrentConvention(Expense $expense): void
    {
        $path = trim((string) ($expense->receipt_document_path ?? ''));
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return;
        }

        $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));
        $targetPath = $this->resolveDocumentPath($expense, $extension, $path);
        if ($targetPath === $path) {
            $expense->receipt_document_name = basename($targetPath);

            return;
        }

        Storage::disk('local')->move($path, $targetPath);
        $expense->receipt_document_path = $targetPath;
        $expense->receipt_document_name = basename($targetPath);
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

    private function deleteDocument(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function sendDocumentResponse(Expense $expense, bool $download)
    {
        if (! is_string($expense->receipt_document_path) || $expense->receipt_document_path === '') {
            abort(404, 'Document not found');
        }

        if (! Storage::disk('local')->exists($expense->receipt_document_path)) {
            abort(404, 'Document not found');
        }

        $path = Storage::disk('local')->path($expense->receipt_document_path);
        $name = $expense->receipt_document_name ?: basename($expense->receipt_document_path);
        $name = str_replace('"', '', $name);

        if ($download) {
            return response()->download($path, $name);
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function supplierSuggestions(): array
    {
        return Supplier::query()->orderBy('name')->pluck('name')->all();
    }

    private function decimalSearchExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => "CAST({$column} AS CHAR)",
            'pgsql', 'sqlite' => "CAST({$column} AS TEXT)",
            'sqlsrv' => "CONVERT(VARCHAR(255), {$column})",
            default => "CAST({$column} AS CHAR)",
        };
    }
}
