<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((string) $ticket->user_id !== '' && (string) $ticket->user_id === (string) $user->id) {
            return true;
        }

        $userEmail = strtolower(trim((string) ($user->email ?? '')));
        $ticketEmail = strtolower(trim((string) ($ticket->email ?? '')));

        return $userEmail !== '' && $ticketEmail !== '' && $userEmail === $ticketEmail;
    }
}
