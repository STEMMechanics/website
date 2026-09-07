<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

class PricingVersion
{
    public static function assertSelectable(?int $id, ?int $retainedId = null): void
    {
        if ($id === null || $id === $retainedId) {
            return;
        }
        if (! DB::table('finance_pricing_versions')->where('id', $id)->where('archived', false)->where('is_snapshot', false)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['version_id' => 'Choose an active allocation plan. Archived plans remain available on existing records.']);
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
