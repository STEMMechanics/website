<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SentEmail extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'recipient',
        'mailable_class',
        'status',
        'scheduled_for_at',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'scheduled_for_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Boot function from Laravel.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model): void {
            if (empty($model->{$model->getKeyName()}) === true) {
                $model->{$model->getKeyName()} = strtolower(Str::random(15));
            }
        });
    }

    public static function statusBadgeToneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_QUEUED, self::STATUS_SCHEDULED => 'warning',
            self::STATUS_SENT => 'success',
            self::STATUS_SKIPPED => 'gray',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}
