<?php

namespace App\Services;

use App\Jobs\SendWorkshopWelcome;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;

class WorkshopWelcomeService
{
    public function eligible(Workshop $workshop): bool
    {
        return $workshop->welcome_enabled && $workshop->registration === 'tickets'
            && ! in_array($workshop->status, ['cancelled', 'draft'], true)
            && $workshop->effectiveStartsAt()?->isFuture()
            && trim((string) $workshop->welcome_subject) !== '' && trim((string) $workshop->welcome_body) !== '';
    }

    /** @return list<string> */
    public function recipients(Workshop $workshop): array
    {
        return $workshop->tickets()->whereIn('status', Ticket::activePurchasedStatuses())->with('user')->get()
            ->flatMap(fn (Ticket $ticket) => [$ticket->email, $ticket->user?->email])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();
    }

    public function isRecipient(Workshop $workshop, string $email): bool
    {
        return $workshop->tickets()->whereIn('status', Ticket::activePurchasedStatuses())
            ->where(fn ($query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orWhereHas('user', fn ($user) => $user->whereRaw('LOWER(TRIM(email)) = ?', [$email])))
            ->exists();
    }

    public function queueForBooking(Workshop $workshop): void
    {
        try {
            $this->queueDue($workshop);
        } catch (\Throwable $exception) {
            // A welcome failure must not retry an already queued booking confirmation.
            // The minute scheduler can pick up bookings missed here.
            report($exception);
        }
    }

    public function queueDue(?Workshop $only = null): int
    {
        $count = 0;
        $query = Workshop::query()->where('welcome_enabled', true)->where('welcome_send_at', '<=', now())
            ->where('ends_at', '>', now())->whereNotIn('status', ['cancelled', 'draft']);
        if ($only) {
            $query->whereKey($only->id);
        }
        foreach ($query->lazyById(100) as $workshop) {
            if (! $this->eligible($workshop)) {
                continue;
            }
            foreach ($this->recipients($workshop) as $email) {
                $count += DB::transaction(function () use ($workshop, $email): int {
                    // The database constraint also guards scheduler/checkout races on different workers.
                    $inserted = DB::table('workshop_welcome_deliveries')->insertOrIgnore([
                        'workshop_id' => $workshop->id, 'generation' => $workshop->welcome_generation,
                        'email' => $email, 'status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if (! $inserted) {
                        // A cancelled delivery was never sent; a later active booking can receive it.
                        $reactivated = DB::table('workshop_welcome_deliveries')->where('workshop_id', $workshop->id)
                            ->where('generation', $workshop->welcome_generation)->where('email', $email)->where('status', 'cancelled')
                            ->update(['status' => 'queued', 'updated_at' => now()]);
                        if (! $reactivated) {
                            return 0;
                        }
                    }
                    $id = DB::table('workshop_welcome_deliveries')->where('workshop_id', $workshop->id)
                        ->where('generation', $workshop->welcome_generation)->where('email', $email)->value('id');
                    DB::afterCommit(function () use ($id): void {
                        try {
                            SendWorkshopWelcome::dispatch((int) $id);
                        } catch (\Throwable $exception) {
                            DB::table('workshop_welcome_deliveries')->where('id', $id)->where('status', 'queued')->update([
                                'status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now(),
                            ]);
                            report($exception);
                        }
                    });

                    return 1;
                });
            }
        }

        return $count;
    }
}
