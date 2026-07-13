<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('uuid', 64)->nullable()->unique();
            $table->string('username', 80);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_logout_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'username']);
            $table->index(['user_id', 'platform']);
            $table->index('username');
        });

        Schema::create('minecraft_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minecraft_account_id')->constrained('minecraft_accounts')->cascadeOnDelete();
            $table->uuid('session_uuid')->nullable()->unique();
            $table->string('server_name', 100)->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['minecraft_account_id', 'logged_in_at']);
        });

        Schema::create('minecraft_penalties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minecraft_account_id')->nullable()->constrained('minecraft_accounts')->nullOnDelete();
            $table->string('external_id', 120)->nullable()->unique();
            $table->string('uuid', 64);
            $table->string('username', 80);
            $table->string('type', 20);
            $table->text('reason')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->string('by_uuid', 64)->nullable();
            $table->string('by_username', 80)->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->string('lifted_by_uuid', 64)->nullable();
            $table->string('lifted_by_username', 80)->nullable();
            $table->timestamps();

            $table->index(['uuid', 'started_at']);
            $table->index(['type', 'started_at']);
        });

        Schema::create('minecraft_blacklist_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minecraft_account_id')->nullable()->constrained('minecraft_accounts')->nullOnDelete();
            $table->string('uuid', 64)->nullable();
            $table->string('username', 80);
            $table->text('reason')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->timestamp('lifted_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['uuid', 'starts_at']);
            $table->index('lifted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_blacklist_entries');
        Schema::dropIfExists('minecraft_penalties');
        Schema::dropIfExists('minecraft_sessions');
        Schema::dropIfExists('minecraft_accounts');
    }
};
