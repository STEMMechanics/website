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
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->text('useragent');
            $table->string('ip');
            $table->timestamps();
            $table->timestamp('ended_at')->nullable();
        });

        Schema::create('analytics_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('session_id')->unsigned();
            $table->string('type');
            $table->string('path');
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('analytics_sessions')->onDelete('cascade');
        });

        // Migrate old analytics table
        $analytics = DB::table('analytics')
            ->select(
                'session',
                DB::raw('MAX(useragent) as useragent'),
                DB::raw('MAX(ip) as ip'),
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('MIN(updated_at) as updated_at'))
            ->groupBy('session')
            ->get();
        foreach ($analytics as $sessionItem) {
            $ip         = $sessionItem->ip;
            $useragent  = $sessionItem->useragent;
            $session_id = $sessionItem->session;
            $created_at = $sessionItem->created_at;
            $updated_at = $sessionItem->updated_at;

            // Create a new row in analytics_sessions
            $new_session_id = DB::table('analytics_sessions')->insertGetId([
                'id' => $session_id,
                'useragent' => $useragent,
                'ip' => $ip,
                'created_at' => $created_at,
                'updated_at' => $updated_at
            ]);

            $requests = DB::table('analytics')->where('session', $session_id)->select('type', 'attribute', 'created_at', 'updated_at')->get();
            $ended_at = $sessionItem->created_at;
                        
            foreach($requests as $requestItem) {
                if($ended_at < $requestItem->created_at) {
                    $ended_at = $requestItem->created_at;
                }

                DB::table('analytics_requests')->insert([
                    'session_id' => $new_session_id,
                    'type' => $requestItem->type,
                    'path' => $requestItem->attribute,
                    'created_at' => $requestItem->created_at,
                    'updated_at' => $requestItem->updated_at,
                ]);
            }

            DB::table('analytics_sessions')->where('id', $new_session_id)->update(['ended_at' => $ended_at]);
        }

        Schema::dropIfExists('analytics');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('session')->nullable(false);
            $table->string('type');
            $table->string('attribute')->default('');
            $table->text('useragent');
            $table->string('ip');
            $table->timestamps();
        });

        $sessions = DB::table('analytics_sessions')->get();
        foreach ($sessions as $session) {
            $requests = DB::table('analytics_requests')->where('session_id', $session->id)->get();
            foreach($requests as $request) {
                DB::table('analytics')->insert([
                    'session' => $session->id,
                    'type' => $request->type,
                    'attribute' => $request->path,
                    'ip' => $session->ip,
                    'useragent' => $session->useragent,
                    'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('analytics_requests');
        Schema::dropIfExists('analytics_sessions');
    }
};
