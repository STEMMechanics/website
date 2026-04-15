<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::query()->with('creator');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('supplier', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('invoice_id', 'like', '%'.$search.'%');
            });
        }

        if ($request->boolean('no_attachment')) {
            $query->where(function ($builder): void {
                $builder->whereNull('receipt_document_path')
                    ->orWhere('receipt_document_path', '');
            });
        }

        $expenses = $query->orderBy('paid_on', 'desc')->orderBy('created_at', 'desc')->paginate(20)->onEachSide(1);

        return view('admin.expense.index', [
            'expenses' => $expenses,
        ]);
    }

    public function create()
    {
        return view('admin.expense.edit', [
            'supplierSuggestions' => $this->supplierSuggestions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $expense = new Expense();
        $expense->fill($validated);
        $expense->created_by = Auth::id();
        $expense->save();

        $this->replaceDocument($expense, $request->file('receipt_document_file'));
        $this->renameDocumentToCurrentConvention($expense);
        $expense->save();

        session()->flash('message', 'Expense has been recorded');
        session()->flash('message-title', 'Expense recorded');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.expense.index');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expense.edit', [
            'expense' => $expense,
            'supplierSuggestions' => $this->supplierSuggestions(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $this->validateRequest($request);

        $expense->fill($validated);
        $expense->save();

        $this->replaceDocument($expense, $request->file('receipt_document_file'));
        $this->renameDocumentToCurrentConvention($expense);
        $expense->save();

        session()->flash('message', 'Expense has been updated');
        session()->flash('message-title', 'Expense updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(Expense $expense)
    {
        $this->deleteDocument($expense->receipt_document_path);
        $expense->delete();

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
            'supplier' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'invoice_id' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['nullable', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['required', 'numeric', 'min:0'],
            'receipt_document_file' => ['nullable', 'file', 'max:'.$maxSize],
        ]);
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
        return Expense::query()
            ->whereNotNull('supplier')
            ->whereRaw("TRIM(supplier) <> ''")
            ->select('supplier')
            ->distinct()
            ->orderBy('supplier')
            ->pluck('supplier')
            ->map(fn ($supplier) => trim((string) $supplier))
            ->filter(fn ($supplier) => $supplier !== '')
            ->values()
            ->all();
    }
}
