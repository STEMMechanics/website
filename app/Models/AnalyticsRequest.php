<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsRequest extends Model
{
    use HasFactory;


    /**
     * Model Boot.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (AnalyticsRequest $analytics) {
            if (isset($analytics->session_id) !== true) {
                $request = request();
                if ($request !== null) {
                    $session = AnalyticsSession::where('ip', $request->ip())
                        ->where('useragent', $request->userAgent())
                        ->where('ended_at', '>=', now()->subMinutes(30))
                        ->first();
                    if ($session === null) {
                        $session = AnalyticsSession::create([
                            'ip'    => $request->ip(),
                            'useragent' => $request->userAgent(),
                            'ended_at'  => now()
                        ]);
                    }

                    $analytics->session_id = $session->id;
                }
            }
        });
    }

    /**
     * Return the Analytics Session model.
     *
     * @return BelongsTo
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSession::class, 'id', 'session_id');
    }
}
