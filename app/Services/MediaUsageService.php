<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaUsageService
{
    private const MEDIA_URL_PATTERN = '~(?:https?:\/\/[^"\'<>\s]+)?\/media\/download\/([^"\'?<>\s]+)~i';

    /**
     * @return array<int, string>
     */
    public function usedMediaNames(): array
    {
        $usedMediaNames = [];

        $this->collectPivotReferences($usedMediaNames);
        $this->collectDirectReferences($usedMediaNames);
        $this->collectContentReferences($usedMediaNames);

        return array_keys($usedMediaNames);
    }

    /**
     * @param array<string, bool> $usedMediaNames
     */
    private function collectPivotReferences(array &$usedMediaNames): void
    {
        if (! Schema::hasTable('mediables')) {
            return;
        }

        foreach (DB::table('mediables')
            ->select('media_name')
            ->whereNotNull('media_name')
            ->cursor() as $row) {
            $mediaName = trim((string) ($row->media_name ?? ''));
            if ($mediaName !== '') {
                $usedMediaNames[$mediaName] = true;
            }
        }
    }

    /**
     * @param array<string, bool> $usedMediaNames
     */
    private function collectDirectReferences(array &$usedMediaNames): void
    {
        $this->markColumnValues($usedMediaNames, 'workshops', 'hero_media_name');
        $this->markColumnValues($usedMediaNames, 'posts', 'hero_media_name');
        $this->markColumnValues($usedMediaNames, 'custom_pages', 'hero_media_name');
        $this->markColumnValues($usedMediaNames, 'products', 'hero_media_name');
        $this->markColumnValues($usedMediaNames, 'users', 'avatar_media_name');
        $this->markColumnValues($usedMediaNames, 'store_order_item_downloads', 'media_name');
    }

    /**
     * @param array<string, bool> $usedMediaNames
     */
    private function collectContentReferences(array &$usedMediaNames): void
    {
        $this->markContentMatches($usedMediaNames, 'workshops', 'content');
        $this->markContentMatches($usedMediaNames, 'posts', 'content');
        $this->markContentMatches($usedMediaNames, 'custom_pages', 'content');
    }

    /**
     * @param array<string, bool> $usedMediaNames
     */
    private function markColumnValues(array &$usedMediaNames, string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->cursor() as $row) {
            $mediaName = trim((string) ($row->{$column} ?? ''));
            if ($mediaName !== '') {
                $usedMediaNames[$mediaName] = true;
            }
        }
    }

    /**
     * @param array<string, bool> $usedMediaNames
     */
    private function markContentMatches(array &$usedMediaNames, string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->cursor() as $row) {
            $content = trim((string) ($row->{$column} ?? ''));
            if ($content === '') {
                continue;
            }

            if (preg_match_all(self::MEDIA_URL_PATTERN, $content, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $mediaName = trim(rawurldecode((string) $match));
                if ($mediaName !== '') {
                    $usedMediaNames[$mediaName] = true;
                }
            }
        }
    }
}
