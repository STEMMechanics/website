<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $latest = DB::table('finance_pricing_versions')->orderByDesc('effective_from')->orderByDesc('id')->first();
        if ($latest === null) {
            return;
        }

        $rules = json_decode($latest->rules, true, 512, JSON_THROW_ON_ERROR);
        $changed = false;
        foreach ($rules as &$rule) {
            if ($rule['basis'] === 'venue') {
                $rule['basis'] = 'venue_hour';
                $rule['rate_cents'] = 4500;
                unset($rule['extra_cents']);
                $changed = true;
            }
        }
        unset($rule);
        if (! $changed) {
            return;
        }

        DB::table('finance_pricing_versions')->insert([
            'name' => $latest->name.' — flat venue rate',
            'effective_from' => max($latest->effective_from, now()->toDateString()),
            'rules' => json_encode($rules, JSON_THROW_ON_ERROR),
            'prices' => $latest->prices,
            'created_by' => $latest->created_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Pricing versions may already be referenced by budgets; preserve their history.
    }
};
