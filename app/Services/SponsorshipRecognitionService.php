<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\SponsorshipRecognitionApprovalRequested;
use App\Models\Sponsor;
use App\Models\SponsorshipPayment;
use App\Models\SponsorshipRecognitionLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SponsorshipRecognitionService
{
    public function groups(): array
    {
        $levels = $this->levels();
        $groups = [];
        foreach ($levels as $level) $groups[$level->name] = [];
        if ($levels->isEmpty()) $groups['Sponsors'] = [];

        foreach ($this->eligibleSponsorGroups() as $members) {
            $sponsor = $this->representative($members);
            $total = $this->completedTotalFor($members);
            $level = $this->assignedLevelFor($members, $levels) ?? $levels->first(fn (SponsorshipRecognitionLevel $row) => $total >= (float) $row->minimum_total);
            $name = $level?->name ?? ($levels->last()?->name ?? 'Sponsors');
            if ($sponsor->publicLabel() !== '') $groups[$name][] = $sponsor;
        }

        foreach ($groups as $name => $sponsors) {
            usort($sponsors, fn (Sponsor $a, Sponsor $b) => strcasecmp($a->publicLabel(), $b->publicLabel()));
            $groups[$name] = $sponsors;
        }

        return array_filter($groups, static fn (array $sponsors) => $sponsors !== []);
    }

    /** @return Collection<int, Sponsor> */
    public function pendingApprovalSponsors(): Collection
    {
        return Sponsor::query()
            ->where(fn ($query) => $query
                ->whereHas('sponsorships.payments', fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED))
                ->orWhereHas('manualSupports'))
            ->where(fn ($query) => $query
                ->where(fn ($individual) => $individual
                    ->where('sponsor_type', 'individual')
                    ->where('recognition_public', true)
                    ->whereNull('recognition_approved_at'))
                ->orWhere(fn ($organisation) => $organisation
                    ->where('sponsor_type', 'organisation')
                    ->where(function ($organisation) {
                        $organisation->whereHas('organisation', fn ($record) => $record
                            ->where('sponsorship_recognition_public', true)
                            ->whereNull('sponsorship_recognition_approved_at'))
                            ->orWhere(fn ($legacy) => $legacy
                                ->whereNull('organisation_id')
                                ->where('recognition_public', true)
                                ->whereNull('recognition_approved_at'));
                    })))
            ->with('organisation')
            ->get()
            ->filter(fn (Sponsor $sponsor) => $sponsor->needsRecognitionApproval())
            ->groupBy(fn (Sponsor $sponsor) => $sponsor->entityIdentityKey())
            ->map(fn ($members) => $members->sortByDesc('updated_at')->first())
            ->filter()
            ->values();
    }

    public function pendingApprovalCount(): int
    {
        return $this->pendingApprovalSponsors()->count();
    }

    public function notifyPendingApproval(Sponsor $sponsor): void
    {
        $sponsor->loadMissing('organisation');
        if (! $sponsor->needsRecognitionApproval()) {
            return;
        }

        if ($sponsor->sponsor_type === 'organisation' && $sponsor->organisation) {
            $organisation = $sponsor->organisation;
            if ($organisation->sponsorship_recognition_approval_notified_at !== null) {
                return;
            }
            $organisation->forceFill(['sponsorship_recognition_approval_notified_at' => now()])->saveQuietly();
        } else {
            if ($sponsor->recognition_approval_notified_at !== null) {
                return;
            }
            $sponsor->forceFill(['recognition_approval_notified_at' => now()])->saveQuietly();
        }

        $adminUrl = route('admin.sponsorship.sponsor.show', $sponsor);
        foreach (app(AdminRecipientService::class)->emails() as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new SponsorshipRecognitionApprovalRequested($sponsor, $adminUrl),
            ))->onQueue('mail');
        }
    }

    public function homepageMajorSponsors(int $limit = 6): Collection
    {
        $levels = $this->levels();
        $majorLevel = $levels->first();
        if (! $majorLevel) return new Collection();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $sponsors = $this->eligibleSponsorGroups()
            ->map(function ($members) use ($levels, $majorLevel, $monthStart, $monthEnd) {
                $sponsor = $this->representative($members);
                $total = $this->completedTotalFor($members);
                $isMajor = $this->assignedLevelFor($members, $levels)?->id === $majorLevel->id
                    || $total >= (float) $majorLevel->minimum_total;

                return (object) ['sponsor' => $sponsor, 'total' => $total, 'eligible' => $isMajor
                    && $sponsor->sponsor_type === 'organisation'
                    && $sponsor->publicLabel() !== ''
                    && $members->contains(fn (Sponsor $member) => $this->activeDuringMonth($member, $monthStart, $monthEnd))];
            })
            ->filter(fn (object $group) => $group->eligible)
            ->sort(function (object $a, object $b): int {
                $byAmount = $b->total <=> $a->total;
                return $byAmount !== 0 ? $byAmount : strcasecmp($a->sponsor->publicLabel(), $b->sponsor->publicLabel());
            })
            ->take(max(0, $limit))
            ->map(fn (object $group) => $group->sponsor)
            ->values();

        return new Collection($sponsors->all());
    }

    private function activeDuringMonth(Sponsor $sponsor, Carbon $monthStart, Carbon $monthEnd): bool
    {
        foreach ($sponsor->manualSupports as $support) {
            if ($support->starts_on && $support->starts_on->lte($monthEnd)
                && (! $support->ends_on || $support->ends_on->gte($monthStart))) {
                return true;
            }
        }

        foreach ($sponsor->sponsorships as $sponsorship) {
            if ($sponsorship->frequency === 'monthly') {
                $startedAt = $sponsorship->started_at ?? $sponsorship->created_at;
                if ($startedAt && $startedAt->lte($monthEnd) && $sponsorship->status === 'active') {
                    return true;
                }

                $effectiveEnd = $sponsorship->square_cancel_at?->copy()->endOfDay() ?? $sponsorship->cancelled_at;
                if ($startedAt && $startedAt->lte($monthEnd)
                    && $sponsorship->status === 'cancelled'
                    && $effectiveEnd && $effectiveEnd->gte($monthStart)) {
                    return true;
                }
            }

            if ($sponsorship->frequency === 'one_time'
                && $sponsorship->payments->contains(fn (SponsorshipPayment $payment) => $payment->paid_at
                    && $payment->paid_at->gte($monthStart) && $payment->paid_at->lte($monthEnd))) {
                return true;
            }
        }

        return false;
    }

    private function levels(): Collection
    {
        return SponsorshipRecognitionLevel::query()
            ->whereNull('project_id')
            ->where('enabled', true)
            ->orderByDesc('minimum_total')
            ->orderBy('sort_order')
            ->get();
    }

    private function eligibleSponsors(): Collection
    {
        return Sponsor::query()
            ->publiclyRecognized()
            ->where(fn ($query) => $query
                ->whereHas('sponsorships.payments', fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED))
                ->orWhereHas('manualSupports'))
            ->with(['organisation', 'sponsorships' => fn ($query) => $query
                ->whereHas('payments', fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED))
                ->with(['payments' => fn ($payments) => $payments->where('status', SponsorshipPayment::STATUS_COMPLETED)]), 'manualSupports.recognitionLevel'])
            ->get();
    }

    private function eligibleSponsorGroups(): \Illuminate\Support\Collection
    {
        return $this->eligibleSponsors()
            ->groupBy(fn (Sponsor $sponsor) => $sponsor->entityIdentityKey())
            ->values();
    }

    private function representative(\Illuminate\Support\Collection $members): Sponsor
    {
        return $members->sort(function (Sponsor $a, Sponsor $b): int {
            $score = fn (Sponsor $sponsor): int => (int) ($sponsor->recognition_logo_path !== null)
                + (int) ($sponsor->website_url !== null) * 2
                + (int) ($sponsor->publicLabel() !== '') * 4;
            $byProfile = $score($b) <=> $score($a);

            return $byProfile !== 0 ? $byProfile : ($b->updated_at?->getTimestamp() <=> $a->updated_at?->getTimestamp());
        })->first();
    }

    private function assignedLevelFor(\Illuminate\Support\Collection $members, Collection $levels): ?SponsorshipRecognitionLevel
    {
        return $members
            ->map(fn (Sponsor $sponsor) => $this->assignedLevel($sponsor, $levels))
            ->filter()
            ->sortByDesc(fn (SponsorshipRecognitionLevel $level) => (float) $level->minimum_total)
            ->first();
    }

    private function completedTotalFor(\Illuminate\Support\Collection $members): float
    {
        return (float) $members->sum(fn (Sponsor $sponsor) => $this->completedTotal($sponsor));
    }

    private function assignedLevel(Sponsor $sponsor, Collection $levels): ?SponsorshipRecognitionLevel
    {
        return $sponsor->manualSupports
            ->pluck('recognitionLevel')
            ->filter()
            ->filter(fn (SponsorshipRecognitionLevel $level) => $levels->contains('id', $level->id))
            ->sortByDesc(fn (SponsorshipRecognitionLevel $level) => (float) $level->minimum_total)
            ->first();
    }

    private function completedTotal(Sponsor $sponsor): float
    {
        $unlinkedManualValue = $sponsor->manualSupports
            ->filter(fn ($support) => $support->sponsorship_id === null)
            ->sum('value_amount');

        return (float) $sponsor->sponsorships->flatMap->payments->sum('total_amount')
            + (float) $unlinkedManualValue;
    }
}
