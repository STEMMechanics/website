<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            foreach (DB::table('finance_pricing_versions')->get() as $plan) {
                $rules = json_decode($plan->rules, true, 512, JSON_THROW_ON_ERROR);
                $changed = false;
                foreach ($rules as &$rule) {
                    if ($rule['basis'] === 'venue') {
                        $rule['basis'] = 'venue_hour';
                        $rule['rate_cents'] = 4500;
                        $changed = true;
                    }
                    if (array_key_exists('extra_cents', $rule)) {
                        unset($rule['extra_cents']);
                        $changed = true;
                    }
                }
                unset($rule);
                if ($changed) {
                    DB::table('finance_pricing_versions')->where('id', $plan->id)->update([
                        'rules' => json_encode($rules, JSON_THROW_ON_ERROR), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Do not reintroduce the retired first/additional-hour pricing model.
    }
};
