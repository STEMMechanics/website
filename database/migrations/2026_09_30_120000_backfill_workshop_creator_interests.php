<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $workshops = DB::table('workshops')
            ->where('registration', 'interest')
            ->get(['id', 'user_id']);

        if ($workshops->isEmpty()) {
            return;
        }

        $users = DB::table('users')
            ->whereIn('id', $workshops->pluck('user_id')->filter()->unique()->values())
            ->get(['id', 'firstname', 'surname', 'email', 'phone'])
            ->keyBy('id');

        foreach ($workshops as $workshop) {
            $user = $users->get($workshop->user_id);
            if ($user === null || DB::table('workshop_interests')
                ->where('workshop_id', $workshop->id)
                ->where('user_id', $user->id)
                ->exists()) {
                continue;
            }

            $firstname = trim((string) ($user->firstname ?? ''));
            $surname = trim((string) ($user->surname ?? ''));
            $email = strtolower(trim((string) ($user->email ?? '')));
            $name = trim($firstname.' '.$surname);
            if ($name === '') {
                $name = str_contains($email, '@') ? substr($email, 0, strpos($email, '@')) : 'Member';
            }

            DB::table('workshop_interests')->insertOrIgnore([
                'workshop_id' => $workshop->id,
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'phone' => trim((string) ($user->phone ?? '')),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep registrations when rolling back this data migration.
    }
};
