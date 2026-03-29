<?php

namespace Tests\Unit;

use App\Support\HtmlContentTransformer;
use Tests\TestCase;

class HtmlContentTransformerTest extends TestCase
{
    public function testCollapseSectionsForDisplayForcesPublicSectionsClosed(): void
    {
        $html = <<<'HTML'
<details data-type="collapsible-section" open class="sm-collapsible-node"><summary class="sm-collapsible-node__summary"><span class="sm-collapsible-node__summary-title">Section</span><span class="sm-collapsible-node__chevron" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span></summary><div class="sm-collapsible-node__content"><p>Body</p></div></details>
HTML;

        $output = HtmlContentTransformer::collapseSectionsForDisplay($html);

        $this->assertStringNotContainsString(' open', $output);
        $this->assertStringContainsString('sm-collapsible-node--public', $output);
        $this->assertStringContainsString('sm-collapsible-node__content', $output);
    }
}
