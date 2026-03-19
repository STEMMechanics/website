<?php

namespace Tests\Unit;

use App\Support\InvoiceDueDate;
use PHPUnit\Framework\TestCase;

class InvoiceDueDateTest extends TestCase
{
    public function test_it_keeps_business_day_due_dates_28_days_later(): void
    {
        $dueDate = InvoiceDueDate::fromIssueDate('2026-03-20');

        $this->assertSame('2026-04-17', $dueDate->toDateString());
    }

    public function test_it_rolls_weekend_due_dates_to_the_next_business_day(): void
    {
        $saturdayDueDate = InvoiceDueDate::fromIssueDate('2026-03-21');
        $sundayDueDate = InvoiceDueDate::fromIssueDate('2026-03-22');

        $this->assertSame('2026-04-20', $saturdayDueDate->toDateString());
        $this->assertSame('2026-04-20', $sundayDueDate->toDateString());
    }
}
