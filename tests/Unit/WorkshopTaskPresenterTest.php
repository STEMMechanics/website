<?php

namespace Tests\Unit;

use App\Support\WorkshopTaskPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkshopTaskPresenterTest extends TestCase
{
    #[Test]
    public function it_groups_repeated_prefixes_and_strips_them_from_task_labels(): void
    {
        $groups = WorkshopTaskPresenter::grouped([
            (object) ['name' => 'Social Media: Pre-workshop post'],
            (object) ['name' => 'Photographs: Wide shot'],
            (object) ['name' => 'social media: Post-workshop post'],
            (object) ['name' => 'Photographs: Close-up'],
        ]);

        $this->assertSame(['Social Media', 'Photographs'], array_column($groups, 'heading'));
        $this->assertSame(['Pre-workshop post', 'Post-workshop post'], array_column($groups[0]['tasks'], 'label'));
        $this->assertSame(['Wide shot', 'Close-up'], array_column($groups[1]['tasks'], 'label'));
    }

    #[Test]
    public function it_keeps_single_or_incomplete_prefixes_as_ungrouped_full_names(): void
    {
        $groups = WorkshopTaskPresenter::grouped([
            (object) ['name' => 'Setup: Tables'],
            (object) ['name' => 'Pack equipment'],
            (object) ['name' => 'Invalid:'],
        ]);

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['heading']);
        $this->assertSame(
            ['Setup: Tables', 'Pack equipment', 'Invalid:'],
            array_column($groups[0]['tasks'], 'label'),
        );
    }
}
