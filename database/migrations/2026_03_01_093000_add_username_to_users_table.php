<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $username = User::generateUniqueUsernameFromEmail(
                (string) ($user->email ?? ''),
                (string) $user->id,
                false
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
};
