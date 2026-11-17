<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

class PricingVersion
{
    /**
     * Active versions for document price editors, plus a retained version when it
     * has since been archived or replaced by a saved revision.
     *
     * @return array<int, array{id: int, name: string, effective_from: string, archived: bool, is_snapshot: bool, pricing: array<string, mixed>}>
     */
    public static function editorOptions(?int $retainedId = null): array
    {
        $categoryNames = DB::table('finance_categories')->pluck('name', 'id');

        return DB::table('finance_pricing_versions')
            ->where(function ($query) use ($retainedId): void {
                $query->where('is_snapshot', false)->where('archived', false);
                if ($retainedId !== null) {
                    $query->orWhere('id', $retainedId);
                }
            })
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->get()
            ->map(function (object $version) use ($categoryNames): array {
                $rules = json_decode((string) $version->rules, true) ?: [];
                foreach ($rules as &$rule) {
                    $rule['category_name'] = $categoryNames[$rule['category_id'] ?? ''] ?? 'Cost centre';
                }
                unset($rule);

                return [
                    'id' => (int) $version->id,
                    'name' => (string) $version->name,
                    'effective_from' => (string) $version->effective_from,
                    'archived' => (bool) $version->archived,
                    'is_snapshot' => (bool) $version->is_snapshot,
                    'pricing' => array_merge(json_decode((string) $version->prices, true) ?: [], [
                        'rules' => $rules,
                        'clean_prices' => true,
                    ]),
                ];
            })
            ->all();
    }

    public static function assertSelectable(?int $id, ?int $retainedId = null, string $field = 'version_id'): void
    {
        if ($id === null || $id === $retainedId) {
            return;
        }
        if (! DB::table('finance_pricing_versions')->where('id', $id)->where('archived', false)->where('is_snapshot', false)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Choose an active pricing plan. Archived plans remain available on existing records.']);
        }
    }

    public static function forDate(string $date, ?int $selectedId = null): object
    {
        if ($selectedId !== null) {
            return DB::table('finance_pricing_versions')->where('id', $selectedId)->firstOrFail();
        }
        $defaultId = DB::table('finance_settings')->where('id', 1)->value('default_pricing_version_id');
        return DB::table('finance_pricing_versions')->where('id', $defaultId)->where('is_snapshot', false)->where('archived', false)->first()
            ?? DB::table('finance_pricing_versions')->where('is_snapshot', false)->where('archived', false)->orderBy('id')->firstOrFail();
    }
}
