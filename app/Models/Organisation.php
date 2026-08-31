<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $casts = ['account_terms_days' => 'integer'];

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
