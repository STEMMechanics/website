<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class SponsorshipBillingScheduleService
{
    public const TIMEZONE = 'Australia/Brisbane';

    /**
     * Start with the full monthly amount, then keep the original calendar-day
     * anchor for card payments or invoice issue dates. If sign-up is on the
     * month's final day, later cycles also use each month's final day.
     *
     * @return array{amount_cents:int, billing_period:string, billing_anchor_date:string, next_payment_date:string, billing_label:string}
     */
    public function initialPayment(float $monthlyAmount, ?CarbonImmutable $startedAt = null): array
    {
        $startedAt = ($startedAt ?? CarbonImmutable::now(self::TIMEZONE))
            ->setTimezone(self::TIMEZONE)
            ->startOfDay();
        $monthlyAmountCents = (int) round($monthlyAmount * 100);

        return [
            'amount_cents' => max(1, $monthlyAmountCents),
            'billing_period' => $startedAt->toDateString(),
            'billing_anchor_date' => $startedAt->toDateString(),
            'next_payment_date' => $this->nextPaymentDate($startedAt->toDateString(), $startedAt->toDateString()),
            'billing_label' => $this->billingLabel($startedAt),
        ];
    }

    public function nextPaymentDate(string $billingPeriod, ?string $anchorDate = null): string
    {
        $billingDate = CarbonImmutable::parse($billingPeriod, self::TIMEZONE)->startOfDay();
        $anchor = CarbonImmutable::parse($anchorDate ?: $billingPeriod, self::TIMEZONE)->startOfDay();
        $nextMonth = $billingDate
            ->startOfMonth()
            ->addMonthNoOverflow()
            ->startOfMonth();

        if ($anchor->day === $anchor->daysInMonth) {
            return $nextMonth->endOfMonth()->toDateString();
        }

        return $nextMonth->day(min($anchor->day, $nextMonth->daysInMonth))->toDateString();
    }

    public function billingLabel(?CarbonImmutable $startedAt = null): string
    {
        $anchor = ($startedAt ?? CarbonImmutable::now(self::TIMEZONE))->setTimezone(self::TIMEZONE)->startOfDay();
        if ($anchor->day === $anchor->daysInMonth) {
            return 'the last day of each month';
        }

        $suffix = match ($anchor->day % 100) {
            11, 12, 13 => 'th',
            default => match ($anchor->day % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            },
        };

        return 'the '.$anchor->day.$suffix.' of each month, or the last day when a month is shorter';
    }
}
