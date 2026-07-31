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
        'notes',
    ];

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
            ->withPivot(['role', 'is_primary'])
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
