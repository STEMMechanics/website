<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class InvoiceDueDate
{
    public static function fromIssueDate(DateTimeInterface|string $issueDate, int $termDays = 28): Carbon
    {
        $dueDate = Carbon::parse($issueDate)->startOfDay()->addDays($termDays);

        while ($dueDate->isWeekend()) {
            $dueDate->addDay();
        }

        return $dueDate;
    }
}
