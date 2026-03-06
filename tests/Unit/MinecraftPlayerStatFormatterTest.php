<?php

namespace Tests\Unit;

use App\Support\MinecraftPlayerStatFormatter;
use Tests\TestCase;

class MinecraftPlayerStatFormatterTest extends TestCase
{
    public function test_play_time_uses_hours_when_description_reports_hours(): void
    {
        $formatted = MinecraftPlayerStatFormatter::formatValue(
            'play_time',
            0.2929583333333333,
            'Total play time recorded by the server in hours.'
        );

        $this->assertSame('17m 35s', $formatted);
    }

    public function test_play_time_falls_back_to_ticks_formatting(): void
    {
        $formatted = MinecraftPlayerStatFormatter::formatValue(
            'play_time',
            72000,
            'Total play time recorded by the server in ticks.'
        );

        $this->assertSame('1h', $formatted);
    }

    public function test_distance_kilometers_are_displayed_in_human_readable_units(): void
    {
        $smallDistance = MinecraftPlayerStatFormatter::formatValue(
            'distance_walked_km',
            0.00304,
            'Distance walked by the player in kilometers.'
        );
        $largeDistance = MinecraftPlayerStatFormatter::formatValue(
            'distance_total_moved_km',
            12.3456,
            'Combined travel distance in kilometers.'
        );

        $this->assertSame('3.04 m', $smallDistance);
        $this->assertSame('12.35 km', $largeDistance);
    }

    public function test_distance_meters_are_displayed_in_human_readable_units(): void
    {
        $smallDistance = MinecraftPlayerStatFormatter::formatValue(
            'distance_fallen_m',
            7.5,
            'Distance fallen by the player in meters.'
        );
        $largeDistance = MinecraftPlayerStatFormatter::formatValue(
            'distance_fallen_m',
            1452.3,
            'Distance fallen by the player in meters.'
        );

        $this->assertSame('7.50 m', $smallDistance);
        $this->assertSame('1.45 km', $largeDistance);
    }

    public function test_time_in_bucket_stats_are_treated_as_hours(): void
    {
        $formatted = MinecraftPlayerStatFormatter::formatValue(
            'time_in_nether',
            0.75,
            'Time spent in the nether.'
        );

        $this->assertSame('45m', $formatted);
    }
}
