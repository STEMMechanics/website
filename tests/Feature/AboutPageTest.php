<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_updated_sections(): void
    {
        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('Practical STEM learning, delivered with care, clarity, and real-world experience.');
        $response->assertSee('What STEMMechanics does');
        $response->assertSee('James Collins');
        $response->assertSee('Alex Rivera');
        $response->assertSee('Talk about a program');
    }
}
