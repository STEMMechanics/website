<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_HOLD = 0;

    public const STATUS_PAID = 1;

    public const STATUS_DONE = self::STATUS_PAID;

    public const STATUS_RELEASED = 2;

    public const STATUS_CANCELLED = 3;

    public const STATUS_PENDING_DOOR = 4;

    public const STATUS_PENDING_XFER = 5;

    public const STATUS_REISSUED = 6;

    public const STATUS_ACCOUNT = 7;

    protected $fillable = [
        'reference_code',
        'status',
        'user_id',
        'workshop_id',
        'invoice_id',
        'invoice_line_id',
        'firstname',
        'surname',
        'email',
        'phone',
        'attended_at',
        'reissued_to_ticket_id',
        'reissued_from_ticket_id',
        'is_early_bird',
    ];

    protected $casts = [
        'status' => 'integer',
        'attended_at' => 'datetime',
        'is_early_bird' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            if (! self::supportsReferenceCodes()) {
                return;
            }

            if (trim((string) $ticket->reference_code) !== '') {
                return;
            }

            $ticket->reference_code = self::generateUniqueReferenceCode();
        });
    }

    public function getRouteKeyName(): string
    {
        return self::supportsReferenceCodes() ? 'reference_code' : 'id';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'paid',
            self::STATUS_RELEASED => 'released',
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_PENDING_DOOR => 'pending-door',
            self::STATUS_PENDING_XFER => 'pending-xfer',
            self::STATUS_ACCOUNT => 'account',
            self::STATUS_REISSUED => $this->reissuedStatusLabel(),
            default => 'hold',
        };
    }

    public function getCustomerStatusLabelAttribute(): string
    {
        return match ((int) $this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_PENDING_DOOR => 'Reserved (Pay at Door)',
            self::STATUS_PENDING_XFER => 'Awaiting Bank Transfer',
            self::STATUS_ACCOUNT => 'Account',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REISSUED => $this->reissuedCustomerStatusLabel(),
            self::STATUS_HOLD => 'Checkout in Progress',
            default => 'Reserved',
        };
    }

    public static function activePurchasedStatuses(): array
    {
        return [
            self::STATUS_PAID,
            self::STATUS_DONE,
            self::STATUS_PENDING_DOOR,
            self::STATUS_PENDING_XFER,
            self::STATUS_ACCOUNT,
        ];
    }

    public static function inactiveStatuses(): array
    {
        return [
            self::STATUS_CANCELLED,
            self::STATUS_REISSUED,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function reissuedToTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissued_to_ticket_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function reissuedFromTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissued_from_ticket_id');
    }

    public function isEarlyBirdTicket(): bool
    {
        return (bool) $this->is_early_bird;
    }

    private function reissuedStatusLabel(): string
    {
        $reference = trim((string) ($this->reissuedToTicket->reference_code ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($this->reissued_to_ticket_id ?? ''));
        }

        if ($reference === '') {
            return 'reissued';
        }

        return 'reissued-'.$reference;
    }

    private function reissuedCustomerStatusLabel(): string
    {
        $reference = trim((string) ($this->reissuedToTicket->reference_code ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($this->reissued_to_ticket_id ?? ''));
        }

        if ($reference === '') {
            return 'Reissued';
        }

        return 'Reissued as '.$reference;
    }

    private static function generateUniqueReferenceCode(): string
    {
        // Excludes ambiguous characters: 0, O, 1, I, L, B, 8.
        $alphabet = '2345679ACDEFGHJKMNPQRTUVWXYZ';
        $length = 6;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (! self::query()->where('reference_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique ticket reference code.');
    }

    public function ensureReferenceCode(): string
    {
        $reference = trim((string) $this->reference_code);
        if ($reference !== '') {
            return $reference;
        }

        if (! self::supportsReferenceCodes()) {
            return (string) $this->id;
        }

        $this->reference_code = self::generateUniqueReferenceCode();
        if ($this->exists) {
            $this->saveQuietly();
        }

        return (string) $this->reference_code;
    }

    private static function supportsReferenceCodes(): bool
    {
        static $supportsReferenceCodes = null;

        if ($supportsReferenceCodes === null) {
            $supportsReferenceCodes = Schema::hasColumn((new self())->getTable(), 'reference_code');
        }

        return $supportsReferenceCodes;
    }
}
