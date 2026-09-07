<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('finance_pricing_versions')->where('is_snapshot', false)->orderByDesc('id')->value('id');
        DB::table('finance_settings')->where('id', 1)->whereNull('default_pricing_version_id')->update(['default_pricing_version_id' => $id]);
    }

    public function down(): void {}
};
