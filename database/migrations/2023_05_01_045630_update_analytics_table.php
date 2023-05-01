<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('analytics', function (Blueprint $table) {
            $table->bigInteger('session')->nullable(false);
            $table->string('attribute')->default('')->change();
        });

        DB::table('analytics')
        ->where('type', 'pageview')
        ->update(['type' => 'apirequest']);

        $rows = DB::table('analytics')
            ->whereNull('session')
            ->orderBy('created_at', 'asc')
            ->get();

        // Loop through the rows and update `session` based on the logic you described
        $session = 1;
        foreach ($rows as $row) {
            // Check if this is the first row
            if ($row->created_at === $rows->first()->created_at) {
                DB::table('analytics')
                    ->where('id', $row->id)
                    ->update(['session' => $session]);
            } else {
                // Look for a previous row with the same useragent and ip within the last 30 minutes
                $previousRow = DB::table('analytics')
                    ->where('useragent', $row->useragent)
                    ->where('ip', $row->ip)
                    ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 minutes', strtotime($row->created_at))))
                    ->whereNotNull('session')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($previousRow) {
                    // If a previous row is found, set the session to the same value
                    DB::table('analytics')
                        ->where('id', $row->id)
                        ->update(['session' => $previousRow->session]);
                } else {
                    // If no previous row is found, increment the session value
                    $session++;
                    DB::table('analytics')
                        ->where('id', $row->id)
                        ->update(['session' => $session]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('analytics', function (Blueprint $table) {
            $table->dropColumn('session');
        });
    }
};
