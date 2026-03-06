<?php

namespace App\Console\Commands;

use App\Services\MinecraftPlayerStatsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncMinecraftPlayerStatsCommand extends Command
{
    protected $signature = 'minecraft:player-stats:sync
        {--uuid= : Sync one player by UUID}
        {--username= : Sync one player by username}
        {--period= : Sync one period only (all, week, month, year)}';

    protected $description = 'Sync cached STEMCraft player statistics from the Minecraft plugin';

    public function handle(MinecraftPlayerStatsSyncService $minecraftPlayerStatsSyncService): int
    {
        if (! Schema::hasTable('minecraft_player_stats')) {
            $this->warn('The minecraft_player_stats table does not exist yet. Run migrations first.');

            return self::SUCCESS;
        }

        $uuid = trim((string) $this->option('uuid'));
        $username = trim((string) $this->option('username'));
        $period = trim((string) $this->option('period'));

        if ($uuid !== '' && $username !== '') {
            $this->error('Use either --uuid or --username, not both.');

            return self::INVALID;
        }

        try {
            if ($uuid !== '') {
                $playerStat = $minecraftPlayerStatsSyncService->syncUuid($uuid, $period !== '' ? $period : null);

                if ($playerStat === null) {
                    $this->info('No cached player stats were returned for UUID '.$uuid.'.');

                    return self::SUCCESS;
                }

                $this->info('Synced player stats for '.$playerStat->username.' ('.$playerStat->uuid.') for '.($period !== '' ? $playerStat->period : 'all periods').'.');

                return self::SUCCESS;
            }

            if ($username !== '') {
                $playerStat = $minecraftPlayerStatsSyncService->syncUsername($username, $period !== '' ? $period : null);

                if ($playerStat === null) {
                    $this->info('No cached player stats were returned for username '.$username.'.');

                    return self::SUCCESS;
                }

                $this->info('Synced player stats for '.$playerStat->username.' ('.$playerStat->uuid.') for '.($period !== '' ? $playerStat->period : 'all periods').'.');

                return self::SUCCESS;
            }

            $result = $minecraftPlayerStatsSyncService->syncAll($period !== '' ? $period : null);

            $this->info('Minecraft player stats sync complete.');
            $this->line('Periods synced: '.implode(', ', $result['periods_synced']));
            $this->line('Unique players received: '.$result['unique_players_received']);
            $this->line('Unique players saved: '.$result['unique_players_saved']);
            $this->line('Period snapshots received: '.$result['snapshots_received']);
            $this->line('Period snapshots saved: '.$result['snapshots_saved']);
            if ($result['timestamp']) {
                $this->line('Response timestamp: '.$result['timestamp']);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Minecraft player stats sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
