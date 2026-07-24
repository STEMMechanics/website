<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
     * @return array<int, array{type: string, label: string, detail: string, url: string|null, public: bool}>
     */
    public function usagesFor(string $mediaName): array
    {
        $mediaName = trim($mediaName);
        if ($mediaName === '') {
            return [];
        }

        $usages = [];

        $this->collectDirectUsageDetails($usages, $mediaName);
        $this->collectContentUsageDetails($usages, $mediaName);

        return $usages;
    }

    /**
     * @param array<int, array{type: string, label: string, detail: string, url: string|null, public: bool}> $usages
     */
    private function collectDirectUsageDetails(array &$usages, string $mediaName): void
    {
        $this->collectColumnUsageDetails($usages, $mediaName, 'workshops', 'hero_media_name', 'Workshop hero', 'title', 'admin.workshop.edit', true);
        $this->collectColumnUsageDetails($usages, $mediaName, 'posts', 'hero_media_name', 'Post hero', 'title', 'admin.post.edit', true);
        $this->collectColumnUsageDetails($usages, $mediaName, 'custom_pages', 'hero_media_name', 'Page hero', 'title', 'admin.custom-page.edit', true);
        $this->collectColumnUsageDetails($usages, $mediaName, 'products', 'hero_media_name', 'Product hero', 'title', 'admin.shop.product.edit', true);
        $this->collectColumnUsageDetails($usages, $mediaName, 'store_order_item_downloads', 'media_name', 'Store download', 'id', null, false);
    }

    /**
     * @param array<int, array{type: string, label: string, detail: string, url: string|null, public: bool}> $usages
     */
    private function collectColumnUsageDetails(array &$usages, string $mediaName, string $table, string $column, string $type, string $labelColumn, ?string $routeName, bool $public): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (DB::table($table)->where($column, $mediaName)->get() as $row) {
            $label = trim((string) ($row->{$labelColumn} ?? ''));
            $id = $row->id ?? null;
            $usages[] = [
                'type' => $type,
                'label' => $label !== '' ? $label : '#'.$id,
                'detail' => $table.'.'.$column,
                'url' => $routeName !== null && $id !== null && Route::has($routeName) ? route($routeName, $id) : null,
                'public' => $public,
            ];
        }
    }

    /**
     * @param array<int, array{type: string, label: string, detail: string, url: string|null, public: bool}> $usages
     */
    private function collectContentUsageDetails(array &$usages, string $mediaName): void
    {
        $this->collectContentUsageDetailsForTable($usages, $mediaName, 'workshops', 'content', 'Workshop content', 'title', 'admin.workshop.edit', true);
        $this->collectContentUsageDetailsForTable($usages, $mediaName, 'posts', 'content', 'Post content', 'title', 'admin.post.edit', true);
        $this->collectContentUsageDetailsForTable($usages, $mediaName, 'custom_pages', 'content', 'Page content', 'title', 'admin.custom-page.edit', true);
    }

    /**
     * @param array<int, array{type: string, label: string, detail: string, url: string|null, public: bool}> $usages
     */
    private function collectContentUsageDetailsForTable(array &$usages, string $mediaName, string $table, string $column, string $type, string $labelColumn, string $routeName, bool $public): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (DB::table($table)->where($column, 'like', '%'.$mediaName.'%')->cursor() as $row) {
            $content = trim((string) ($row->{$column} ?? ''));
            if ($content === '' || ! str_contains($content, $mediaName)) {
                continue;
            }

            $usages[] = [
                'type' => $type,
                'label' => trim((string) ($row->{$labelColumn} ?? '')) ?: '#'.$row->id,
                'detail' => 'Embedded in content',
                'url' => Route::has($routeName) ? route($routeName, $row->id) : null,
                'public' => $public,
            ];
        }
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
