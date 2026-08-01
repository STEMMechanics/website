<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('primary_organisation_id')
                ->nullable()
                ->after('company')
                ->constrained('organisations')
                ->nullOnDelete();
        });

        DB::table('organisation_user')
            ->where('is_primary', true)
            ->orderBy('id')
            ->each(function ($link): void {
                DB::table('users')
                    ->where('id', $link->user_id)
                    ->whereNull('primary_organisation_id')
                    ->update(['primary_organisation_id' => $link->organisation_id]);
            });

        Schema::table('organisation_user', function (Blueprint $table): void {
            $table->dropColumn('is_primary');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('company');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('company')->nullable()->after('surname');
        });

        DB::table('users')
            ->whereNotNull('primary_organisation_id')
            ->orderBy('id')
            ->each(function ($user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'company' => DB::table('organisations')->where('id', $user->primary_organisation_id)->value('name'),
                ]);
            });

        Schema::table('organisation_user', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false)->after('role');
        });

        DB::table('users')
            ->whereNotNull('primary_organisation_id')
            ->orderBy('id')
            ->each(function ($user): void {
                DB::table('organisation_user')
                    ->where('user_id', $user->id)
                    ->where('organisation_id', $user->primary_organisation_id)
                    ->update(['is_primary' => true]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_organisation_id');
        });
    }
};
