<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Council/organisation funding belongs to the delivery, not its free registration tickets. */
class WorkshopFunding
{
    public function entries(InvoiceLine $line): Collection
    {
        if ($line->kind === 'workshop') {
            return ! empty($line->details_json['workshop']['linked_workshop_id']) ? collect([$line]) : collect();
        }
        $entries = collect();
        if ($line->kind !== 'multi_workshop') {
            return $entries;
        }
        foreach ($line->details_json['multi_workshop']['rows'] ?? [] as $index => $row) {
            $settings = $row['details_json']['workshop'] ?? [];
            if (empty($settings['linked_workshop_id'])) {
                continue;
            }
            $entry = clone $line;
            $entry->details_json = ['workshop' => array_merge($settings, ['hours' => $row['workshop_hours'], 'seats' => $row['workshop_seats'], 'venue_supplied' => $row['venue_supplied'] ?? false, 'supplied_categories' => $row['supplied_categories'] ?? []]), 'funding_row_index' => $index];
            $entries->push($entry);
        }

        return $entries;
    }

    public function linkedLines(?array $workshopIds = null): Collection
    {
        $lines = InvoiceLine::with('invoice')->where(function ($query) use ($workshopIds) {
            $query->where(function ($single) use ($workshopIds) {
                $single->where('kind', 'workshop')->whereNotNull('details_json->workshop->linked_workshop_id');
                if ($workshopIds !== null) {
                    $single->whereIn('details_json->workshop->linked_workshop_id', $workshopIds);
                }
            })->orWhere('kind', 'multi_workshop');
        })->orderBy('id')->get();

        return $lines->flatMap(fn ($line) => $this->entries($line))->filter(fn ($line) => $workshopIds === null || in_array($line->details_json['workshop']['linked_workshop_id'], $workshopIds, true))->values();
    }

    public function lines(string $workshopId): Collection
    {
        return $this->linkedLines([$workshopId]);
    }

    public function invoiceIds(string $workshopId): array
    {
        return Ticket::where('workshop_id', $workshopId)->whereNotNull('invoice_id')->pluck('invoice_id')
            ->merge($this->lines($workshopId)->pluck('invoice_id'))->unique()->values()->all();
    }

    public function assumptions(Workshop $workshop): array
    {
        $line = $this->lines($workshop->id)->first(fn ($line) => $line->invoice->status !== Invoice::STATUS_CANCELLED);
        if (! $line) {
            return [];
        }
        $settings = $line->details_json['workshop'];
        $basis = $settings['allocation_basis'] ?? 'manual';

        return [
            'funding_line_id' => $line->id,
            'allocation_basis' => $basis,
            'participants' => match ($basis) {
                'capacity' => (int) $workshop->max_tickets,
                'tickets' => $workshop->tickets()->whereIn('status', Ticket::activePurchasedStatuses())->count(),
                'attendance' => $this->attendanceCount($workshop),
                default => (int) ($settings['allocation_seats'] ?? $settings['seats'] ?? 0),
            },
            'hours' => (float) ($settings['hours'] ?? $workshop->teachingHours()),
            'venue_supplied' => (bool) ($settings['venue_supplied'] ?? false),
            'supplied_categories' => $settings['supplied_categories'] ?? [],
        ];
    }

    public static function settings(array $settings): array
    {
        $data = Validator::make($settings, [
            'linked_workshop_id' => 'nullable|string|exists:workshops,id',
            'allocation_basis' => 'nullable|in:manual,capacity,tickets,attendance',
            'allocation_seats' => 'nullable|integer|min:0|max:10000',
        ])->validate();
        if (empty($data['linked_workshop_id'])) {
            return [];
        }
        $basis = $data['allocation_basis'] ?? 'manual';
        if ($basis === 'manual') {
            Validator::make(['allocation_seats' => $data['allocation_seats'] ?? $settings['seats'] ?? null], ['allocation_seats' => 'required|integer|min:0|max:10000'])->validate();
        }

        return ['linked_workshop_id' => $data['linked_workshop_id'], 'allocation_basis' => $basis,
            'allocation_seats' => (int) ($data['allocation_seats'] ?? $settings['seats'] ?? 0)];
    }

    /** One authoritative allocation basis per delivery; prevent ambiguous duplicate funding. */
    public function validateLinks(Invoice $invoice): void
    {
        DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
        $seen = [];
        foreach ($invoice->lines()->whereIn('kind', ['workshop', 'multi_workshop'])->get()->flatMap(fn ($line) => $this->entries($line)) as $line) {
            $id = $line->details_json['workshop']['linked_workshop_id'] ?? null;
            if (! $id || $invoice->status === Invoice::STATUS_CANCELLED) {
                continue;
            }
            if (isset($seen[$id]) || $this->lines($id)->contains(fn ($other) => $other->invoice_id !== $invoice->id && $other->invoice->status !== Invoice::STATUS_CANCELLED)) {
                throw ValidationException::withMessages(['workshop_funding' => 'This workshop already has a funding line. Use one funding line per workshop so its allocation settings are unambiguous.']);
            }
            $seen[$id] = true;
        }
    }

    /** Issued invoices may change internal links, never their billed quantities or amounts. */
    public function saveLinks(Invoice $invoice, array $input): bool
    {
        $changed = false;
        foreach ($input as $id => $settings) {
            $line = $invoice->lines()->whereIn('kind', ['workshop', 'multi_workshop'])->whereKey($id)->firstOrFail();
            $details = $line->details_json ?? [];
            if ($line->kind === 'multi_workshop') {
                foreach ($settings['rows'] ?? [] as $index => $rowSettings) {
                    if (! isset($details['multi_workshop']['rows'][$index])) {
                        abort(422, 'Unknown workshop row.');
                    }
                    $row = &$details['multi_workshop']['rows'][$index];
                    $current = $row['details_json']['workshop'] ?? [];
                    $normalized = self::settings(array_merge($current, ['seats' => $row['workshop_seats']], $rowSettings));
                    $changed = $changed || ($current['linked_workshop_id'] ?? null) !== ($normalized['linked_workshop_id'] ?? null);
                    $row['details_json']['workshop'] = $normalized;
                    unset($row);
                }
                $line->update(['details_json' => $details]);

                continue;
            }
            $current = $details['workshop'] ?? [];
            $normalized = self::settings(array_merge($current, $settings));
            foreach (['linked_workshop_id', 'allocation_basis', 'allocation_seats'] as $field) {
                unset($current[$field]);
            }
            $details['workshop'] = array_merge($current, $normalized);
            $changed = $changed || ($details['workshop']['linked_workshop_id'] ?? null) !== ($line->details_json['workshop']['linked_workshop_id'] ?? null);
            $line->update(['details_json' => $details]);
        }
        $this->validateLinks($invoice);
        $invoice->unsetRelation('lines');

        return $changed;
    }

    public function catalog(): array
    {
        $workshops = Workshop::with('location')
            ->withCount(['tickets as registered_ticket_count' => fn ($query) => $query->whereIn('status', Ticket::activePurchasedStatuses())])
            ->orderByDesc('starts_at')
            ->get();
        $activeStatuses = Ticket::activePurchasedStatuses();
        $ticketAttendanceCounts = Ticket::query()
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('attended_at')
            ->select('workshop_id')
            ->selectRaw('COUNT(*) AS attended_count')
            ->groupBy('workshop_id')
            ->pluck('attended_count', 'workshop_id');
        $sessionAttendanceCounts = DB::table('workshop_session_attendance')
            ->join('tickets', 'tickets.id', '=', 'workshop_session_attendance.ticket_id')
            ->whereIn('tickets.status', $activeStatuses)
            ->select('workshop_session_attendance.workshop_id')
            ->selectRaw('COUNT(DISTINCT workshop_session_attendance.ticket_id) AS attended_count')
            ->groupBy('workshop_session_attendance.workshop_id')
            ->pluck('attended_count', 'workshop_id');

        return $workshops->map(function ($workshop) use ($ticketAttendanceCounts, $sessionAttendanceCounts): array {
            $attendanceCount = $sessionAttendanceCounts->get($workshop->id) ?? $ticketAttendanceCounts->get($workshop->id, 0);

            return [
                'id' => (string) $workshop->id,
                'label' => $workshop->title.' · '.($workshop->starts_at?->format('d M Y') ?? 'Date TBC').' · '.$workshop->getLocationName(),
                'title' => $workshop->title,
                'date' => $workshop->starts_at?->format('Y-m-d'),
                'hours' => $workshop->teachingHours(),
                'capacity' => $workshop->max_tickets,
                'tickets' => $workshop->getAttribute('registered_ticket_count'),
                'attendance' => (int) $attendanceCount,
            ];
        })->all();
    }

    private function attendanceCount(Workshop $workshop): int
    {
        if ($workshop->isCourse()) {
            return (int) DB::table('workshop_session_attendance')
                ->join('tickets', 'tickets.id', '=', 'workshop_session_attendance.ticket_id')
                ->where('workshop_session_attendance.workshop_id', $workshop->id)
                ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
                ->distinct('workshop_session_attendance.ticket_id')
                ->count('workshop_session_attendance.ticket_id');
        }

        return (int) $workshop->tickets()
            ->whereIn('status', Ticket::activePurchasedStatuses())
            ->whereNotNull('attended_at')
            ->count();
    }
}
