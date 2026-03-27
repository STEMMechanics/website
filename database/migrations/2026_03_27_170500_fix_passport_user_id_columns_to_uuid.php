<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('oauth_auth_codes')) {
            DB::statement('ALTER TABLE oauth_auth_codes MODIFY user_id CHAR(36) NOT NULL');
        }

        if (Schema::hasTable('oauth_access_tokens')) {
            DB::statement('ALTER TABLE oauth_access_tokens MODIFY user_id CHAR(36) NULL');
        }

        if (Schema::hasTable('oauth_device_codes')) {
            DB::statement('ALTER TABLE oauth_device_codes MODIFY user_id CHAR(36) NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('oauth_auth_codes')) {
            DB::statement('ALTER TABLE oauth_auth_codes MODIFY user_id BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasTable('oauth_access_tokens')) {
            DB::statement('ALTER TABLE oauth_access_tokens MODIFY user_id BIGINT UNSIGNED NULL');
        }

        if (Schema::hasTable('oauth_device_codes')) {
            DB::statement('ALTER TABLE oauth_device_codes MODIFY user_id BIGINT UNSIGNED NULL');
        }
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
