<?php

namespace App\Services;

use App\Models\User;

class AdminRecipientService
{
    /** @return array<int, string> */
    public function emails(): array
    {
        $emails = User::query()->whereHas('groups', fn ($query) => $query->where('slug', 'admin'))
            ->pluck('email')->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
        if ($emails !== []) {
            return $emails;
        }
        $fallback = strtolower(trim((string) config('mail.from.address', '')));

        return $fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL) ? [$fallback] : [];
    }
}
