<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class NavbarThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_site_uses_a_tinted_navbar_background(): void
    {
        $html = $this->renderNavbarForHost('test.stemmechanics.com.au');

        $this->assertStringContainsString('class="relative z-120 isolate border-b border-purple-300 shadow bg-purple-50"', $html);
        $this->assertStringContainsString('repeating-linear-gradient(45deg', $html);
    }

    public function test_non_test_sites_keep_the_default_white_navbar_background(): void
    {
        $html = $this->renderNavbarForHost('localhost');

        $this->assertStringContainsString('class="relative z-120 isolate shadow bg-white"', $html);
        $this->assertStringNotContainsString('bg-purple-50', $html);
        $this->assertStringNotContainsString('repeating-linear-gradient(45deg', $html);
    }

    private function renderNavbarForHost(string $host): string
    {
        $request = Request::create('https://'.$host.'/', 'GET');
        $request->server->set('HTTP_HOST', $host);
        $request->server->set('SERVER_NAME', $host);

        $this->app->instance('request', $request);

        return view('components.navbar')->render();
    }
}
