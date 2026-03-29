<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiToolbarTest extends TestCase
{
    public function test_toolbar_defaults_to_sm_breakpoint(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.toolbar>
                <x-slot:left>Left</x-slot:left>
                <x-slot:right>Right</x-slot:right>
            </x-ui.toolbar>
            BLADE);

        $this->assertStringContainsString('sm:flex-row', $html);
        $this->assertStringContainsString('sm:gap-4', $html);
        $this->assertStringContainsString('sm:items-center', $html);
    }

    public function test_toolbar_can_use_a_custom_breakpoint(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.toolbar break="md">
                <x-slot:left>Left</x-slot:left>
                <x-slot:right>Right</x-slot:right>
            </x-ui.toolbar>
            BLADE);

        $this->assertStringContainsString('md:flex-row', $html);
        $this->assertStringContainsString('md:gap-4', $html);
        $this->assertStringContainsString('md:items-center', $html);
        $this->assertStringNotContainsString('sm:flex-row', $html);
    }
}
