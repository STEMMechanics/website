<?php

namespace App\Support;

use App\Models\EmailSubscriptions;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Support\Facades\DB;

class UserAnonymizer
{
    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $email = trim((string) ($user->email ?? ''));
            if ($email !== '') {
                EmailSubscriptions::query()->where('email', $email)->delete();
            }

            $user->tokens()->delete();
            $user->backupCodes()->delete();
            $user->groups()->delete();

            if (! $user->isAnonymized()) {
                (new AuditLogObserver)->deleted($user);
            }

            $user->forceFill(User::filterToExistingDatabaseColumns([
                'firstname' => null,
                'surname' => null,
                'company' => null,
                'email' => null,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'phone' => null,
                'shipping_address' => null,
                'shipping_address2' => null,
                'shipping_city' => null,
                'shipping_postcode' => null,
                'shipping_state' => null,
                'shipping_country' => null,
                'billing_address' => null,
                'billing_address2' => null,
                'billing_city' => null,
                'billing_postcode' => null,
                'billing_state' => null,
                'billing_country' => null,
                'tfa_secret' => null,
                'anonymized_at' => now(),
            ]))->saveQuietly();
        });
    }

}
