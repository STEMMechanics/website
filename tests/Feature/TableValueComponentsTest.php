<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TableValueComponentsTest extends TestCase
{
    public function test_date_time_values_only_offer_a_break_between_date_and_time(): void
    {
        foreach ([
            ['Sep 5, 2026 9:30 am', 'Sep 5, 2026', '9:30 am'],
            ['Sep 5 2026, 9:30 pm', 'Sep 5 2026,', '9:30 pm'],
            ['2026-09-05 21:30:15', '2026-09-05', '21:30:15'],
        ] as [$value, $date, $time]) {
            $html = Blade::render('<x-ui.date-time :value="$value" />', compact('value'));
            $this->assertStringContainsString('<span class="sm-no-break">'.$date.'</span>', $html);
            $this->assertStringContainsString('<span class="sm-no-break">'.$time.'</span>', $html);
            $this->assertSame(1, substr_count($html, '<wbr>'));
        }
    }

    public function test_date_only_time_only_and_missing_values_stay_together(): void
    {
        foreach (['Sep 5, 2026', '9:30 am', '-'] as $value) {
            $html = Blade::render('<x-ui.date-time :value="$value" />', compact('value'));
            $this->assertStringContainsString('<span class="sm-no-break">'.$value.'</span>', $html);
            $this->assertStringNotContainsString('<wbr>', $html);
        }
    }

    public function test_slot_text_is_escaped_when_rendered(): void
    {
        $value = '<script>alert("test")</script>';
        $html = Blade::render('<x-ui.date-time>{{ $value }}</x-ui.date-time>', compact('value'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
