<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationMembershipRequest extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'organisation_id',
        'user_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notified_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'admin_notified_at' => 'datetime',
    ];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
