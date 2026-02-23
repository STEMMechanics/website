<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;

class TicketReissueService
{
    public function hasAttendeeChanges(Ticket $ticket, array $details): bool
    {
        $normalized = $this->normalizeDetails($details);

        return $normalized['firstname'] !== trim((string) ($ticket->firstname ?? ''))
            || $normalized['surname'] !== trim((string) ($ticket->surname ?? ''))
            || $normalized['email'] !== strtolower(trim((string) ($ticket->email ?? '')))
            || $normalized['phone'] !== trim((string) ($ticket->phone ?? ''));
    }

    public function reissue(Ticket $ticket, array $details): array
    {
        $normalized = $this->normalizeDetails($details);
        $this->ensureAttendeeGhostUser($normalized);

        if (! $this->hasAttendeeChanges($ticket, $normalized)) {
            return [
                'changed' => false,
                'old_ticket' => $ticket,
                'new_ticket' => $ticket,
                'email_changed' => false,
                'old_email' => strtolower(trim((string) ($ticket->email ?? ''))),
                'new_email' => strtolower(trim((string) ($ticket->email ?? ''))),
            ];
        }

        $oldEmail = strtolower(trim((string) ($ticket->email ?? '')));
        $oldStatus = (int) $ticket->status;

        $newTicket = new Ticket();
        $newTicket->status = $oldStatus;
        $newTicket->user_id = $ticket->user_id;
        $newTicket->workshop_id = $ticket->workshop_id;
        $newTicket->invoice_id = $ticket->invoice_id;
        $newTicket->invoice_line_id = $ticket->invoice_line_id;
        $newTicket->firstname = $normalized['firstname'];
        $newTicket->surname = $normalized['surname'];
        $newTicket->email = $normalized['email'];
        $newTicket->phone = $normalized['phone'];
        $newTicket->reissued_from_ticket_id = $ticket->id;
        $newTicket->save();

        $ticket->status = Ticket::STATUS_REISSUED;
        $ticket->reissued_to_ticket_id = $newTicket->id;
        $ticket->save();

        return [
            'changed' => true,
            'old_ticket' => $ticket,
            'new_ticket' => $newTicket,
            'email_changed' => $oldEmail !== $normalized['email'],
            'old_email' => $oldEmail,
            'new_email' => $normalized['email'],
        ];
    }

    private function normalizeDetails(array $details): array
    {
        return [
            'firstname' => trim((string) ($details['firstname'] ?? '')),
            'surname' => trim((string) ($details['surname'] ?? '')),
            'email' => strtolower(trim((string) ($details['email'] ?? ''))),
            'phone' => trim((string) ($details['phone'] ?? '')),
        ];
    }

    private function ensureAttendeeGhostUser(array $details): void
    {
        $email = strtolower(trim((string) ($details['email'] ?? '')));
        if ($email === '') {
            return;
        }

        $firstname = trim((string) ($details['firstname'] ?? ''));
        $surname = trim((string) ($details['surname'] ?? ''));
        $phone = trim((string) ($details['phone'] ?? ''));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->firstname = $firstname !== '' ? $firstname : null;
            $user->surname = $surname !== '' ? $surname : null;
            $user->phone = $phone !== '' ? $phone : null;
            $user->save();

            return;
        }

        if ($user->email_verified_at === null) {
            if ($firstname !== '') {
                $user->firstname = $firstname;
            }
            if ($surname !== '') {
                $user->surname = $surname;
            }
            if ($phone !== '') {
                $user->phone = $phone;
            }
            $user->save();
        }
    }
}
