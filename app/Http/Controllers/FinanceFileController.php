<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\FinanceFile;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FinanceFileController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $maxSize = max((int) round(Helpers::getMaxUploadSize() / 1024), 1);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxSize],
        ]);

        $uploaded = $validated['file'];
        $path = $uploaded->store('finance/private', 'local');

        $file = FinanceFile::query()->create([
            'path' => $path,
            'original_name' => (string) $uploaded->getClientOriginalName(),
            'mime_type' => $uploaded->getMimeType(),
            'size' => (int) $uploaded->getSize(),
            'user_id' => (string) auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'file' => $this->serialize($file),
        ]);
    }

    public function view(FinanceFile $financeFile)
    {
        return $this->sendFileResponse($financeFile, false);
    }

    public function download(FinanceFile $financeFile)
    {
        return $this->sendFileResponse($financeFile, true);
    }

    public function impact(Request $request, FinanceFile $financeFile): JsonResponse
    {
        $validated = $request->validate([
            'context_type' => ['required', 'in:quote,invoice'],
            'context_id' => ['required'],
        ]);

        $contextType = (string) $validated['context_type'];
        $contextId = trim((string) $validated['context_id']);

        $associations = $this->associationRows($financeFile->id);

        $contextModelType = $contextType === 'quote' ? Quote::class : Invoice::class;
        $isLinkedToContext = collect($associations)->contains(fn (array $row) => $row['fileable_type'] === $contextModelType && $row['fileable_id'] === $contextId);
        $totalAssociations = count($associations);
        $otherAssociations = collect($associations)
            ->filter(fn (array $row) => !($row['fileable_type'] === $contextModelType && $row['fileable_id'] === $contextId))
            ->map(fn (array $row) => $this->labelAssociation($row))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'file' => $this->serialize($financeFile),
            'total_associations' => $totalAssociations,
            'is_linked_to_context' => $isLinkedToContext,
            'suggested_action' => $totalAssociations > 1 ? 'unlink' : 'delete',
            'other_associations' => $otherAssociations,
        ]);
    }

    public function updateAssociation(Request $request, FinanceFile $financeFile): JsonResponse
    {
        $validated = $request->validate([
            'context_type' => ['required', 'in:quote,invoice'],
            'context_id' => ['required'],
            'action' => ['required', 'in:unlink,delete'],
        ]);

        $contextType = (string) $validated['context_type'];
        $contextId = trim((string) $validated['context_id']);
        $action = (string) $validated['action'];
        $contextModelType = $contextType === 'quote' ? Quote::class : Invoice::class;

        if ($action === 'unlink') {
            DB::table('finance_fileables')
                ->where('finance_file_id', $financeFile->id)
                ->where('fileable_type', $contextModelType)
                ->where('fileable_id', $contextId)
                ->where('collection', 'private')
                ->delete();

            return response()->json([
                'success' => true,
                'deleted' => false,
            ]);
        }

        $financeFile->delete();

        return response()->json([
            'success' => true,
            'deleted' => true,
        ]);
    }

    private function sendFileResponse(FinanceFile $financeFile, bool $download)
    {
        $path = trim((string) $financeFile->path);
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found');
        }

        $absolute = Storage::disk('local')->path($path);
        $name = trim((string) $financeFile->original_name) ?: basename($path);
        $name = str_replace('"', '', $name);

        if ($download) {
            return response()->download($absolute, $name);
        }

        return response()->file($absolute, [
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    /**
     * @return array<int, array{fileable_type: string, fileable_id: string}>
     */
    private function associationRows(int $fileId): array
    {
        return DB::table('finance_fileables')
            ->where('finance_file_id', $fileId)
            ->where('collection', 'private')
            ->select(['fileable_type', 'fileable_id'])
            ->get()
            ->map(fn ($row) => [
                'fileable_type' => (string) $row->fileable_type,
                'fileable_id' => (string) $row->fileable_id,
            ])
            ->all();
    }

    /**
     * @param array{fileable_type: string, fileable_id: string} $row
     * @return array{type: string, id: string, label: string}
     */
    private function labelAssociation(array $row): array
    {
        if ($row['fileable_type'] === Quote::class) {
            $quote = Quote::query()->find($row['fileable_id']);
            $label = $quote ? 'Quote '.$quote->quote_number : 'Quote #'.$row['fileable_id'];
            return ['type' => 'quote', 'id' => (string) $row['fileable_id'], 'label' => $label];
        }

        if ($row['fileable_type'] === Invoice::class) {
            $invoice = Invoice::query()->find($row['fileable_id']);
            $label = $invoice ? 'Invoice '.$invoice->invoice_number : 'Invoice #'.$row['fileable_id'];
            return ['type' => 'invoice', 'id' => (string) $row['fileable_id'], 'label' => $label];
        }

        return ['type' => 'other', 'id' => (string) $row['fileable_id'], 'label' => 'Unknown record #'.$row['fileable_id']];
    }

    /**
     * @return array{id:int,name:string,mime_type:string,size:int,view_url:string,download_url:string}
     */
    private function serialize(FinanceFile $file): array
    {
        return [
            'id' => (int) $file->id,
            'name' => (string) $file->original_name,
            'mime_type' => (string) ($file->mime_type ?? ''),
            'size' => (int) $file->size,
            'view_url' => route('admin.finance-file.view', $file),
            'download_url' => route('admin.finance-file.download', $file),
        ];
    }
}

