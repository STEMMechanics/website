<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminWorkshopTicketService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly WorkshopRegistrationGroupService $workshopRegistrationGroups,
        private readonly WorkshopTicketService $workshopTicketService
    ) {}

    /**
     * @param  array{firstname:string, surname:string, email:string, phone:string}  $attendee
     * @return array{ticket: Ticket, invoice: Invoice|null}
     */
    public function create(Workshop $workshop, array $attendee, string $type): array
    {
        if ((string) $workshop->registration !== 'tickets') {
            throw ValidationException::withMessages([
                'manual_ticket_type' => 'This workshop does not use managed tickets.',
            ]);
        }

        $availableTickets = $this->workshopTicketService->availableTickets($workshop);
        if ($availableTickets !== null && $availableTickets < 1) {
            throw ValidationException::withMessages([
                'manual_ticket_type' => 'No tickets are available for this workshop.',
            ]);
        }

        $normalizedType = $type === 'reserve' ? 'reserve' : 'free';
        $ticketPriceAmount = $this->workshopTicketService->ticketPriceAmount($workshop);
        $linkedUserId = $this->resolveLinkedUserId((string) ($attendee['email'] ?? ''));

        $result = DB::transaction(function () use ($attendee, $linkedUserId, $normalizedType, $ticketPriceAmount, $workshop): array {
            $ticket = new Ticket();
            $ticket->status = $normalizedType === 'reserve' && $ticketPriceAmount > 0
                ? Ticket::STATUS_PENDING_DOOR
                : Ticket::STATUS_PAID;
            $ticket->user_id = $linkedUserId;
            $ticket->workshop_id = $workshop->id;
            $ticket->invoice_id = null;
            $ticket->invoice_line_id = null;
            $ticket->firstname = trim((string) ($attendee['firstname'] ?? ''));
            $ticket->surname = trim((string) ($attendee['surname'] ?? ''));
            $ticket->email = strtolower(trim((string) ($attendee['email'] ?? '')));
            $ticket->phone = trim((string) ($attendee['phone'] ?? ''));
            $ticket->save();

            $invoice = null;
            if ((int) $ticket->status === Ticket::STATUS_PENDING_DOOR) {
                $invoice = $this->createReserveInvoice($workshop, $ticket, $ticketPriceAmount, $attendee, $linkedUserId);
                $firstLine = $invoice->lines->first();

                $ticket->invoice_id = $invoice->id;
                $ticket->invoice_line_id = $firstLine?->id;
                $ticket->save();
            }

            return [
                'ticket' => $ticket->fresh(['workshop', 'invoice', 'user']),
                'invoice' => $invoice,
            ];
        });

        $this->workshopRegistrationGroups->assignForTickets(
            [$result['ticket']],
            $linkedUserId !== '' ? $linkedUserId : null
        );
        $this->workshopTicketService->syncManagedTicketStatus($workshop);

        return $result;
    }

    private function resolveLinkedUserId(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return '';
        }

        return (string) (User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNull('anonymized_at')
            ->value('id') ?? '');
    }

    /**
     * @param  array{firstname:string, surname:string, email:string, phone:string}  $attendee
     */
    private function createReserveInvoice(
        Workshop $workshop,
        Ticket $ticket,
        float $ticketPriceAmount,
        array $attendee,
        string $linkedUserId
    ): Invoice {
        $ticketPriceAmount = round($ticketPriceAmount, 2);
        $lineTotalEx = round($ticketPriceAmount / 1.1, 2);
        $taxAmount = round($ticketPriceAmount - $lineTotalEx, 2);
        $ticketReference = $ticket->ensureReferenceCode();

        $invoice = new Invoice();
        $invoice->invoice_number = $this->documentNumbers->nextInvoiceNumber();
        $invoice->user_id = $linkedUserId !== '' ? $linkedUserId : null;
        $invoice->billing_name = trim((string) (($attendee['firstname'] ?? '').' '.($attendee['surname'] ?? '')));
        $invoice->billing_email = strtolower(trim((string) ($attendee['email'] ?? '')));
        $invoice->billing_phone = trim((string) ($attendee['phone'] ?? ''));
        $invoice->status = Invoice::STATUS_ISSUED;
        $invoice->issue_date = Carbon::today();
        $invoice->due_date = $workshop->starts_at
            ? Carbon::parse($workshop->starts_at)->startOfDay()
            : Carbon::today();
        $invoice->subtotal_amount = $lineTotalEx;
        $invoice->gst_amount = $taxAmount;
        $invoice->total_amount = $ticketPriceAmount;
        $invoice->notes = trim(implode("\n", [
            'Generated from admin reserved ticket creation for workshop: '.$workshop->title,
            'Ticket reference: '.$ticketReference,
            'Attendee: '.trim((string) (($attendee['firstname'] ?? '').' '.($attendee['surname'] ?? ''))),
            'Attendee email: '.strtolower(trim((string) ($attendee['email'] ?? ''))),
            'Attendee phone: '.trim((string) ($attendee['phone'] ?? '')),
        ]));
        $invoice->save();

        $line = new InvoiceLine();
        $line->invoice_id = $invoice->id;
        $line->line_number = 1;
        $line->kind = 'ticket';
        $line->description = $workshop->title.' - Ticket '.$ticketReference;
        $line->notes = trim(implode("\n", [
            'Workshop date/time: '.($workshop->starts_at?->format('M j, Y g:i a') ?? '-'),
            'Workshop location: '.((string) $workshop->getLocationName()),
            'Reserved by admin as pay-at-door ticket.',
        ]));
        $line->details_json = [
            'ticket_id' => (int) $ticket->id,
            'ticket_reference' => $ticketReference,
            'workshop_id' => $workshop->id,
            'workshop_title' => $workshop->title,
        ];
        $line->quantity = 1;
        $line->unit_price_ex_tax = $lineTotalEx;
        $line->tax_rate = 0.10;
        $line->line_total_ex_tax = $lineTotalEx;
        $line->tax_amount = $taxAmount;
        $line->line_total_inc_tax = $ticketPriceAmount;
        $line->source_type = null;
        $line->source_id = null;
        $line->save();

        $invoice->setRelation('lines', collect([$line]));

        return $invoice;
    }
}
