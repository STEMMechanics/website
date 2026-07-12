<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable()->after('email');
        });

        $users = DB::table('users')->orderBy('created_at')->orderBy('id')->get(['id', 'email']);
        foreach ($users as $user) {
            $username = $this->generateUniqueUsernameFromEmail(
                (string) ($user->email ?? ''),
                (string) $user->id
            );

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    private function generateUniqueUsernameFromEmail(string $email, string $userId): string
    {
        $localPart = trim((string) Str::of($email)->before('@')->lower());
        $base = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'member';
        $base = trim((string) preg_replace('/[-_.]+/', '-', $base), '-_.') ?: 'member';
        $base = substr($base, 0, 24) ?: 'member';

        $candidate = $base;
        $suffix = 1;

        while (DB::table('users')
            ->where('username', $candidate)
            ->where('id', '!=', $userId)
            ->exists()) {
            $suffix++;
            $candidate = substr($base, 0, max(1, 31 - strlen((string) $suffix))).$suffix;
        }

        return $candidate;
    }
};
