<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use App\Models\WorkshopTemplateTask;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkplanCheckoffController extends Controller
{
    public function invoice(Request $request, \App\Models\Invoice $invoice): JsonResponse
    {
        $data = $request->validate([
            'checked' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'original_notes' => ['present_with:notes', 'nullable', 'string'],
        ]);
        if (!array_key_exists('checked', $data) && !array_key_exists('notes', $data)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['checked' => 'Choose a follow-up action.']);
        }
        return DB::transaction(function () use ($invoice, $data): JsonResponse {
            $locked = \App\Models\Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (array_key_exists('notes', $data)) {
                if ((string) $locked->notes !== (string) ($data['original_notes'] ?? '')) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['notes' => 'These notes have changed elsewhere. Reload the dashboard before editing them.']);
                }
                $locked->notes = $data['notes'];
            }
            if (array_key_exists('checked', $data)) {
                $locked->follow_up_contacted_at = $data['checked'] ? ($locked->follow_up_contacted_at ?? now()) : null;
            }
            $locked->save();

            return response()->json(['notes' => (string) $locked->notes, 'contacted' => $locked->follow_up_contacted_at?->format('j M Y, g:ia')]);
        });
    }

    public function workshop(Request $request, Workshop $workshop): JsonResponse
    {
        $data = $request->validate(['checked' => ['required', 'boolean']]);
        $workshop->update(['workplan_checked' => $data['checked']]);

        return response()->json(['checked' => (bool) $workshop->workplan_checked]);
    }

    public function task(Request $request, Workshop $workshop, WorkshopTemplateTask $task): JsonResponse
    {
        $data = $request->validate(['checked' => ['required', 'boolean']]);
        DB::transaction(function () use ($workshop, $task, $data): void {
            $locked = Workshop::query()->lockForUpdate()->findOrFail($workshop->id);
            abort_unless($locked->pick_list_template_id !== null && (int) $task->pick_list_template_id === (int) $locked->pick_list_template_id, 404);
            $ids = collect($locked->run_sheet_completed_task_ids ?? [])->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => $id === (int) $task->id);
            if ($data['checked']) $ids->push((int) $task->id);
            $locked->update(['run_sheet_completed_task_ids' => $ids->unique()->values()->all()]);
            app(ReminderService::class)->syncWorkshop($locked->fresh());
        });

        return response()->json(['checked' => (bool) $data['checked']]);
    }
}
