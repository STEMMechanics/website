<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon|null $recognition_approved_at
 * @property string|null $recognition_approved_by
 * @property \Illuminate\Support\Carbon|null $recognition_approval_notified_at
 */
class Sponsor extends Model
{
    protected $fillable = [
        'user_id', 'organisation_id', 'email', 'contact_name', 'sponsor_type', 'company_name', 'abn', 'foreign_tax_id', 'country',
        'non_resident_declaration', 'billing_address', 'billing_address2', 'billing_city', 'billing_state',
        'billing_postcode', 'square_customer_id', 'square_card_id', 'recognition_public', 'display_name',
        'recognition_company_name', 'website_url', 'recognition_logo_path', 'recognition_approved_at',
        'recognition_approved_by', 'recognition_approval_notified_at',
    ];

    protected $hidden = [
        'email', 'contact_name', 'company_name', 'abn', 'foreign_tax_id', 'country', 'non_resident_declaration',
        'billing_address', 'billing_address2', 'billing_city', 'billing_state', 'billing_postcode',
        'square_customer_id', 'square_card_id', 'user_id', 'recognition_company_name', 'public_message',
        'organisation_id', 'recognition_approved_at', 'recognition_approved_by', 'recognition_approval_notified_at',
    ];

    protected $casts = [
        'recognition_public' => 'boolean',
        'non_resident_declaration' => 'boolean',
        'recognition_approved_at' => 'datetime',
        'recognition_approval_notified_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /** @return HasMany<Sponsorship, $this> */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'sponsor_id');
    }

    /** @return HasMany<ManualSponsorSupport, $this> */
    public function manualSupports(): HasMany
    {
        return $this->hasMany(ManualSponsorSupport::class, 'sponsor_id');
    }

    /** @return HasMany<SponsorshipMagicLink, $this> */
    public function magicLinks(): HasMany
    {
        return $this->hasMany(SponsorshipMagicLink::class, 'sponsor_id');
    }

    public function publicLabel(): string
    {
        $organisationName = $this->sponsor_type === 'organisation'
            ? ($this->organisation?->name ?: $this->attributes['company_name'] ?? $this->attributes['recognition_company_name'] ?? '')
            : '';

        return trim((string) ($this->display_name ?: $organisationName));
    }

    public function isRecognitionPublic(): bool
    {
        if ($this->sponsor_type === 'organisation' && $this->organisation) {
            return (bool) $this->organisation->sponsorship_recognition_public;
        }

        return (bool) ($this->attributes['recognition_public'] ?? false);
    }

    public function isRecognitionApproved(): bool
    {
        if ($this->sponsor_type === 'organisation' && $this->organisation) {
            return $this->organisation->sponsorship_recognition_approved_at !== null;
        }

        return ($this->attributes['recognition_approved_at'] ?? null) !== null;
    }

    public function needsRecognitionApproval(): bool
    {
        return $this->isRecognitionPublic() && ! $this->isRecognitionApproved();
    }

    public function scopePubliclyRecognized(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(fn (Builder $individual) => $individual
                ->where('sponsor_type', 'individual')
                ->where('recognition_public', true)
                ->whereNotNull('recognition_approved_at'))
                ->orWhere(fn (Builder $organisation) => $organisation
                    ->where('sponsor_type', 'organisation')
                    ->where(function (Builder $organisation): void {
                        $organisation->whereHas('organisation', fn (Builder $record) => $record
                            ->where('sponsorship_recognition_public', true)
                            ->whereNotNull('sponsorship_recognition_approved_at'))
                            ->orWhere(fn (Builder $legacy) => $legacy
                                ->whereNull('organisation_id')
                                ->where('recognition_public', true)
                                ->whereNotNull('recognition_approved_at'));
                    }));
        });
    }

    public function getRecognitionPublicAttribute(mixed $value): bool
    {
        return $this->isRecognitionPublic();
    }

    public function getCompanyNameAttribute(mixed $value): ?string
    {
        return $this->organisation?->name ?: $value;
    }

    public function getAbnAttribute(mixed $value): ?string
    {
        return $this->organisation?->abn ?: $value;
    }

    public function getForeignTaxIdAttribute(mixed $value): ?string
    {
        return $this->organisation?->foreign_tax_id ?: $value;
    }

    public function getWebsiteUrlAttribute(mixed $value): ?string
    {
        return $this->organisation?->website_url ?: $value;
    }

    public function getRecognitionLogoPathAttribute(mixed $value): ?string
    {
        return $this->organisation?->logo_path ?: $value;
    }

    public function getBillingAddressAttribute(mixed $value): ?string
    {
        return $this->organisation?->billing_address ?: $value;
    }

    public function getBillingAddress2Attribute(mixed $value): ?string
    {
        return $this->organisation?->billing_address2 ?: $value;
    }

    public function getBillingCityAttribute(mixed $value): ?string
    {
        return $this->organisation?->billing_city ?: $value;
    }

    public function getBillingStateAttribute(mixed $value): ?string
    {
        return $this->organisation?->billing_state ?: $value;
    }

    public function getBillingPostcodeAttribute(mixed $value): ?string
    {
        return $this->organisation?->billing_postcode ?: $value;
    }

    public function getCountryAttribute(mixed $value): ?string
    {
        return $this->organisation?->billing_country ?: $value;
    }

    public function entityIdentityKey(): string
    {
        if ($this->sponsor_type === 'organisation') {
            if ($this->organisation_id) {
                return 'organisation:'.$this->organisation_id;
            }

            $organisation = trim((string) ($this->company_name ?: $this->recognition_company_name ?: $this->display_name));
            if ($organisation !== '') {
                $organisation = preg_replace('/\s+/u', ' ', $organisation) ?: $organisation;

                return 'organisation:'.mb_strtolower($organisation);
            }
        }

        if ($this->user_id) {
            return 'user:'.$this->user_id;
        }

        $email = trim((string) $this->email);
        if ($email !== '') {
            return 'email:'.mb_strtolower($email);
        }

        return 'sponsor:'.$this->getKey();
    }
}
