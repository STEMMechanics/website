<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        foreach ([
            'minecraft_player_stats',
            'minecraft_messages',
            'minecraft_event_logs',
            'minecraft_webhook_logs',
            'minecraft_sessions',
            'minecraft_penalties',
            'minecraft_blacklist_entries',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::dropIfExists('minecraft_accounts');

        if (Schema::hasTable('site_options')) {
            DB::table('site_options')
                ->whereIn('name', [
                    'minecraft.server-webhook-url',
                    'minecraft.webhook-secret',
                    'minecraft.message-failure-notification-delay-minutes',
                ])
                ->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
