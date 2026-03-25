<?php

namespace Tests\Unit;

use App\Support\ForumContent;
use Tests\TestCase;

class ForumContentTest extends TestCase
{
    public function test_it_can_omit_strikethrough_segments_from_rendered_titles(): void
    {
        $this->assertSame(
            'Need <strong>help</strong> <em>today</em>',
            ForumContent::renderTitleMarkdown('Need **help** ~~soon~~ *today*', false)
        );
    }

    public function test_it_formats_email_preview_text_with_list_items_and_spacing(): void
    {
        $this->assertSame(
            "This is the test that I am testing with:\n- Point 1\n- Point 2\n- Point 3\nAnd this is the last line",
            ForumContent::emailPreviewText('<p>This is the test that I am testing with:</p><ul><li>Point 1</li><li>Point 2</li><li>Point 3</li></ul><p>And this is the last line</p>')
        );
    }

    public function test_it_does_not_insert_blank_lines_between_bullets_when_list_items_wrap_paragraphs(): void
    {
        $this->assertSame(
            "Line 1\n- Line 2\n- Line 3\n- Line 4\nLine 5",
            ForumContent::emailPreviewText('<p>Line 1</p><ul><li><p>Line 2</p></li><li><p>Line 3</p></li><li><p>Line 4</p></li></ul><p>Line 5</p>')
        );
    }
}
