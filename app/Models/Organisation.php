<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $sponsorship_recognition_public
 * @property \Illuminate\Support\Carbon|null $sponsorship_recognition_approved_at
 * @property string|null $sponsorship_recognition_approved_by
 * @property \Illuminate\Support\Carbon|null $sponsorship_recognition_approval_notified_at
 */
class Organisation extends Model
{
    use HasFactory, UUID;

    public const TYPES = [
        'council' => 'Council',
        'library' => 'Library / service',
        'school' => 'School',
        'community_group' => 'Community group',
        'business' => 'Business',
        'government' => 'Government',
        'other' => 'Other',
    ];

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'website_url',
        'logo_path',
        'abn',
        'foreign_tax_id',
        'sponsorship_recognition_public',
        'sponsorship_recognition_approved_at',
        'sponsorship_recognition_approved_by',
        'sponsorship_recognition_approval_notified_at',
        'billing_address',
        'billing_address2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'shipping_address',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'account_terms_days',
        'invoice_email_to',
        'invoice_email_cc',
        'invoice_email_subject',
        'invoice_email_message',
        'notes',
    ];

    protected $casts = [
        'account_terms_days' => 'integer',
        'sponsorship_recognition_public' => 'boolean',
        'sponsorship_recognition_approved_at' => 'datetime',
        'sponsorship_recognition_approval_notified_at' => 'datetime',
    ];

    public function accountTermsDays(): int
    {
        $days = (int) $this->account_terms_days;

        return in_array($days, User::ACCOUNT_TERMS_OPTIONS, true) ? $days : 0;
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Organisation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('firstname')
            ->orderBy('surname');
    }

    /** @return HasMany<Sponsor, $this> */
    public function sponsorshipProfiles(): HasMany
    {
        return $this->hasMany(Sponsor::class, 'organisation_id');
    }

    /**
     * @return HasMany<Workshop, $this>
     */
    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'hosted_for_organisation_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[(string) $this->type] ?? 'Other';
    }

    /**
     * @return array<int, string>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $pending = [(string) $this->id];

        while ($pending !== []) {
            $childIds = self::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
            $childIds = array_values(array_diff($childIds, $ids));
            $ids = [...$ids, ...$childIds];
            $pending = $childIds;
        }

        return $ids;
    }
}
