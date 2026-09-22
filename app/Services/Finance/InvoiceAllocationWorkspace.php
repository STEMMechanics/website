<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Workshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceAllocationWorkspace
{
    public function workshops(Invoice $invoice): Collection
    {
        $ids = $invoice->lines()->get()->flatMap(fn ($line) => app(WorkshopFunding::class)->entries($line))->pluck('details_json.workshop.linked_workshop_id')
            ->merge($invoice->tickets()->pluck('workshop_id'))->unique();
        return Workshop::whereIn('id', $ids)->get()->sortBy(fn ($workshop) => $ids->search($workshop->id))->values();
    }

    public function contexts(Invoice $invoice): Collection
    {
        $service = app(WorkshopAllocation::class);
        return $this->workshops($invoice)->map(fn ($workshop) => ['workshop' => $workshop, 'allocation' => $service->context($workshop), 'state' => $service->state($workshop)]);
    }

    public function invoiceItems(Invoice $invoice): array
    {
        $parts = app(InvoiceAllocationParts::class);
        $invoice->loadMissing('tickets');
        $items = [];
        foreach ($invoice->lines as $line) {
            if ($line->kind === 'multi_workshop') {
                foreach ($line->details_json['multi_workshop']['rows'] ?? [] as $row) {
                    if (empty($row['details_json']['workshop']['linked_workshop_id'])) $items[] = $row['description'];
                }
            } elseif ($parts->lineKey($line, $invoice) === 'invoice') {
                $items[] = $line->description;
            }
        }
        return $items;
    }

    /** Validate the viewed snapshot before this transaction changes invoice funding inputs. */
    public function prepare(Invoice $invoice, array $input): array
    {
        Validator::make(['workshop_allocations' => $input], ['workshop_allocations' => 'array|max:100', 'workshop_allocations.*.source_hash' => 'required|string|size:64'])->validate();
        \Illuminate\Support\Facades\DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
        $workshops = $this->workshops($invoice)->keyBy('id');
        $validated = [];
        foreach ($input as $id => $data) {
            $workshop = $workshops->get($id);
            if (! $workshop) throw ValidationException::withMessages(['workshop_allocations' => 'Only workshops linked to this invoice can be allocated here.']);
            $workshop = Workshop::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! hash_equals(app(WorkshopAllocation::class)->state($workshop)['hash'], $data['source_hash'])) {
                throw ValidationException::withMessages(['workshop_allocations.'.$id => $workshop->title.': Workshop or payment details changed. Refresh and review before saving.']);
            }
            $validated[$id] = $data['source_hash'];
        }
        return $validated;
    }

    /** Called inside the invoice save transaction; stale or invalid plans roll back the whole save. */
    public function save(Invoice $invoice, array $input, string $userId, array $validated = []): void
    {
        Validator::make(['workshop_allocations' => $input], ['workshop_allocations' => 'array|max:100', 'workshop_allocations.*' => 'array'])->validate();
        $workshops = $this->workshops($invoice)->keyBy('id');
        foreach ($input as $id => $data) {
            $workshop = $workshops->get($id);
            if (! $workshop) throw ValidationException::withMessages(['workshop_allocations' => 'Only workshops linked to this invoice can be allocated here.']);
            try {
                if (isset($validated[$id]) && hash_equals($validated[$id], $data['source_hash'] ?? '')) {
                    $data['source_hash'] = app(WorkshopAllocation::class)->state($workshop)['hash'];
                }
                app(WorkshopAllocation::class)->finalise($workshop, $data, $userId);
            } catch (ValidationException $exception) {
                $errors = [];
                foreach ($exception->errors() as $field => $messages) $errors['workshop_allocations.'.$id.'.'.$field] = array_map(fn ($message) => $workshop->title.': '.$message, $messages);
                throw ValidationException::withMessages($errors);
            }
        }
    }
}
