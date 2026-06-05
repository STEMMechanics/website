<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiBadgeTest extends TestCase
{
    public function test_badge_renders_tone_specific_classes(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.badge color="success">Paid</x-ui.badge>
            <x-ui.badge color="warning" size="xs" uppercase="true">Queued</x-ui.badge>
            <x-ui.badge color="purple" variant="solid" icon="fa-solid fa-star">Featured</x-ui.badge>
            BLADE);

        $this->assertStringContainsString('border-emerald-200', $html);
        $this->assertStringContainsString('bg-amber-50', $html);
        $this->assertStringContainsString('uppercase', $html);
        $this->assertStringContainsString('bg-violet-600', $html);
        $this->assertStringContainsString('fa-solid fa-star', $html);
    }
}
