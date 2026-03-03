<?php

namespace Tests\Unit;

use App\Models\MinecraftSession;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MinecraftSessionTest extends TestCase
{
    public function test_formatted_duration_prefers_logged_in_and_logged_out_timestamps(): void
    {
        $session = new MinecraftSession([
            'duration_seconds' => 36003,
        ]);
        $session->logged_in_at = Carbon::parse('2026-03-02 22:00:00');
        $session->logged_out_at = Carbon::parse('2026-03-02 22:00:03');

        $this->assertSame(3, $session->resolvedDurationSeconds());
        $this->assertSame('00:00:03', $session->formattedDuration());
    }

    public function test_formatted_duration_falls_back_to_stored_duration_when_logout_missing(): void
    {
        $session = new MinecraftSession([
            'duration_seconds' => 83,
        ]);
        $session->logged_in_at = Carbon::parse('2026-03-02 22:00:00');
        $session->logged_out_at = null;

        $this->assertSame(83, $session->resolvedDurationSeconds());
        $this->assertSame('00:01:23', $session->formattedDuration());
    }
}
