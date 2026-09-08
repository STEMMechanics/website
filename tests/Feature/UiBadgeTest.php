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

    public function test_badge_consumers_do_not_override_theme_colours(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('/<x-ui\.badge\b(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>/s', file_get_contents($file->getPathname()), $badges);
            foreach ($badges[0] as $badge) {
                preg_match('/\bclass="([^"]*)"/', $badge, $classes);
                $this->assertDoesNotMatchRegularExpression('/(?:bg-|text-(?:red|green|emerald|rose|amber|sky|gray|slate|white)|border-(?:red|green|emerald|rose|amber|sky|gray|slate|white)|sm-banner-)/', $classes[1] ?? '', $file->getPathname());
                $this->assertStringNotContainsString('style=', $badge, $file->getPathname());
            }
        }
    }

    public function test_workshop_statuses_use_shared_badge_themes(): void
    {
        foreach (['open' => 'success', 'cancelled' => 'danger', 'draft' => 'warning', 'full' => 'purple'] as $status => $tone) {
            $actual = Blade::render('<x-ui.workshop-status-badge :status="$status">Status</x-ui.workshop-status-badge>', compact('status'));
            $expected = Blade::render('<x-ui.badge :color="$tone" variant="solid">Status</x-ui.badge>', compact('tone'));
            preg_match('/class="([^"]*)"/', $actual, $actualClasses);
            preg_match('/class="([^"]*)"/', $expected, $expectedClasses);
            $this->assertSame($expectedClasses[1], $actualClasses[1]);
        }
    }
}
