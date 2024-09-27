<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SentEmail extends Model
{
    protected $fillable = ['recipient', 'mailable_class'];
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Boot function from Laravel.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()}) === true) {
                $model->{$model->getKeyName()} = strtolower(Str::random(15));
            }
        });
    }
}
