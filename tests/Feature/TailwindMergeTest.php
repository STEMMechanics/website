<?php

namespace Tests\Feature;

use App\Support\TailwindMerge;
use Illuminate\View\ComponentAttributeBag;
use TalesFromADev\TailwindMerge\TailwindMergeInterface;
use Tests\TestCase;

class TailwindMergeTest extends TestCase
{
    public function test_application_reuses_the_merger_only_within_a_scope(): void
    {
        $first = app(TailwindMerge::class);
        $this->assertSame($first, app(TailwindMerge::class));
        $this->app->forgetScopedInstances();
        $this->assertNotSame($first, app(TailwindMerge::class));
    }

    public function test_repeated_component_styles_are_merged_once_per_scope(): void
    {
        $engine = $this->createMock(TailwindMergeInterface::class);
        $engine->expects($this->once())->method('merge')->with('px-2 px-4')->willReturn('px-4');
        $this->app->scoped(TailwindMerge::class, fn () => new TailwindMerge($engine));

        $this->assertSame('px-4', twMerge(['px-2', 'px-4' => true, 'hidden' => false]));
        $this->assertSame('px-4', twMerge(new ComponentAttributeBag(['class' => ' px-2   px-4 '])));

        $first = app(TailwindMerge::class);
        $this->app->forgetScopedInstances();
        $this->assertNotSame($first, app(TailwindMerge::class));
    }

    public function test_cached_styles_preserve_overrides_and_variants(): void
    {
        foreach (range(1, 2) as $_) {
            $this->assertSame('px-4 hover:bg-blue-500', twMerge('px-2 hover:bg-red-500', 'px-4 hover:bg-blue-500'));
            $this->assertSame('px-2 hover:bg-red-500', twMerge('px-4 hover:bg-blue-500', 'px-2 hover:bg-red-500'));
            $this->assertSame('', twMerge(null, false, ['hidden' => false]));
        }
    }

    public function test_cache_is_bounded_and_evicted_styles_are_recomputed(): void
    {
        $engine = $this->createMock(TailwindMergeInterface::class);
        $engine->expects($this->exactly(514))->method('merge')->willReturnCallback(fn (...$args) => $args[0]);
        $merger = new TailwindMerge($engine);
        for ($i = 0; $i < 513; $i++) {
            $this->assertSame('custom-'.$i, $merger->merge('custom-'.$i));
        }
        $this->assertSame('custom-512', $merger->merge('custom-512'));
        $this->assertSame('custom-0', $merger->merge('custom-0'));
    }
}
