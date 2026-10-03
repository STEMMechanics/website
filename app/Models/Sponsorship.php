<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsorship extends Model
{
    public const CHECKOUT_TYPE_COMMUNITY_SUPPORT = 'coffee';
    public const CHECKOUT_TYPE_BUSINESS = 'business';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'sponsor_id', 'project_id', 'option_id', 'checkout_type', 'invoice_recipient_customized', 'frequency', 'billing_method', 'local_recurring_billing', 'billing_anchor_date', 'amount', 'currency', 'status', 'referral_source',
        'next_payment_date', 'started_at', 'cancelled_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'invoice_recipient_customized' => 'boolean', 'local_recurring_billing' => 'boolean', 'started_at' => 'datetime', 'cancelled_at' => 'datetime', 'billing_anchor_date' => 'date', 'next_payment_date' => 'date'];

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id');
    }

    /** @return BelongsTo<SponsorshipProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(SponsorshipProject::class, 'project_id');
    }

    /** @return BelongsTo<SponsorshipOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(SponsorshipOption::class, 'option_id');
    }

    /** @return HasMany<SponsorshipPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SponsorshipPayment::class, 'sponsorship_id')->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function isRecurring(): bool
    {
        return $this->frequency === 'monthly';
    }
}
