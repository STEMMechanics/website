<?php

namespace App\Support;

use App\Models\EmailSubscriptions;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Support\Facades\DB;

class UserAnonymizer
{
    public function anonymize(User $user, bool $cascadeChildren = true): void
    {
        DB::transaction(function () use ($user, $cascadeChildren): void {
            if ($cascadeChildren) {
                foreach ($user->children()
                    ->whereNull('anonymized_at')
                    ->get()
                    as $child) {
                    if (! $child instanceof User) {
                        continue;
                    }

                    $this->anonymize($child, false);
                }
            }

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
                'parent_user_id' => null,
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
                'avatar_media_name' => null,
                'avatar_mode' => null,
                'avatar_letters' => null,
                'avatar_icon_class' => null,
                'avatar_background_color' => null,
                'avatar_zoom' => 100,
                'avatar_offset_x' => 0,
                'avatar_offset_y' => 0,
                'tfa_secret' => null,
                'username' => User::generateUniqueUsername('deleted', (string) $user->id, true),
                'child_can_select_avatar_media' => true,
                'child_can_use_avatar_camera' => true,
                'anonymized_at' => now(),
            ]))->saveQuietly();
        });
    }

}
