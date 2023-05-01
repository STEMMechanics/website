<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];


    /**
     * Create a new row in the analytics table with the given attributes,
     * automatically assigning a session value based on previous rows.
     *
     * @param  array $attributes Model attributes.
     * @return static
     */
    public static function createWithSession(array $attributes)
    {
        $previousRow = self::where('useragent', $attributes['useragent'])
        ->where('ip', $attributes['ip'])
        ->where('created_at', '>=', now()->subMinutes(30))
        ->whereNotNull('session')
        ->orderBy('created_at', 'desc')
        ->first();

        if ($previousRow !== null) {
            $attributes['session'] = $previousRow->session;
        } else {
            $lastSession = self::max('session');
            $attributes['session'] = ($lastSession + 1);
        }

        return static::create($attributes);
    }
}
