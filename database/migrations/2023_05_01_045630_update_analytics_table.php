<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analytics', function (Blueprint $table) {
            $table->bigInteger('session')->nullable(false);
            $table->string('attribute')->default('')->change();
        });

        DB::table('analytics')
        ->where('type', 'pageview')
        ->update(['type' => 'apirequest']);

        // Set first session
        $session = 0;

        do {
            $rows = DB::table('analytics')
            ->whereNull('session')
            ->orWhere('session', 0)
            ->orderBy('created_at', 'asc')
            ->limit(1)
            ->get();

            if($rows->isEmpty()) {
                break;
            }

            $sessionRow = $rows->first();
            DB::table('analytics')->where('id', $sessionRow->id)->update(['session' => ++$session]);
            $lastSessionUpdate = $sessionRow->created_at;

            do {
                $sameSessionRows = DB::table('analytics')
                ->whereNull('session')
                ->orWhere('session', 0)
                ->where('useragent', $sessionRow->useragent)
                ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime('30 minutes', strtotime($lastSessionUpdate))))
                ->orderBy('created_at', 'desc')
                ->get();

                if($sameSessionRows->isEmpty()) {
                    break;
                }

                $ids = $sameSessionRows->pluck('id')->toArray();
                DB::table('analytics')->whereIn('id', $ids)->update(['session' => $session]);
                $lastSessionUpdate = $sameSessionRows->first()->created_at;
            } while(true);
        } while(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};
