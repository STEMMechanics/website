<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorshipProject extends Model
{
    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'logo_path', 'project_url', 'github_url',
        'enabled', 'sponsorship_enabled', 'is_primary', 'currency', 'allow_custom_amount', 'custom_amount_min',
        'custom_amount_max',
    ];

    protected $casts = [
        'enabled' => 'boolean', 'sponsorship_enabled' => 'boolean', 'is_primary' => 'boolean', 'allow_custom_amount' => 'boolean',
        'custom_amount_min' => 'decimal:2', 'custom_amount_max' => 'decimal:2',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(SponsorshipOption::class, 'project_id')->orderBy('frequency')->orderBy('sort_order')->orderBy('amount');
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'project_id');
    }

    public function manualSupports(): HasMany
    {
        return $this->hasMany(ManualSponsorSupport::class, 'project_id');
    }

    public function recognitionLevels(): HasMany
    {
        return $this->hasMany(SponsorshipRecognitionLevel::class, 'project_id')->orderByDesc('minimum_total')->orderBy('sort_order');
    }

    public function publicSupporterCount(): int
    {
        return Sponsor::query()
            ->publiclyRecognized()
            ->where(fn ($query) => $query
                ->whereHas('sponsorships', fn ($sponsorships) => $sponsorships->where('project_id', $this->id)->whereHas('payments', fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED)))
                ->orWhereHas('manualSupports', fn ($supports) => $supports->where('project_id', $this->id)))
            ->with('organisation')
            ->get(['id', 'organisation_id', 'user_id', 'email', 'sponsor_type', 'company_name', 'recognition_company_name', 'display_name'])
            ->unique(fn (Sponsor $sponsor) => $sponsor->entityIdentityKey())
            ->count();
    }
}
