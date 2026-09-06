<?php

namespace Tests\Feature;

use App\Http\Middleware\NoCache;
use App\Http\Middleware\SecurityHeaders;
use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

class SiteHardeningTest extends TestCase
{
    public function test_login_limit_uses_actual_login_field_and_an_independent_ip_limit(): void
    {
        $limiter = RateLimiter::limiter('login');
        $first = $limiter(Request::create('/login', 'POST', ['login' => 'member@example.com', 'email' => 'one@example.com']));
        $second = $limiter(Request::create('/login', 'POST', ['login' => ' MEMBER@example.com ', 'email' => 'two@example.com']));
        $other = $limiter(Request::create('/login', 'POST', ['login' => 'another@example.com']));
        $this->assertCount(2, $first);
        $this->assertSame($first[1]->key, $second[1]->key);
        $this->assertSame($first[0]->key, $other[0]->key);
        $this->assertNotSame($first[1]->key, $other[1]->key);
    }

    public function test_login_redirects_reject_external_and_ambiguous_destinations(): void
    {
        config(['app.url' => 'https://example.com']);
        foreach (['//evil.test', '/\\evil.test', 'https://evil.test', 'https://example.com@evil.test', "\n/ok", 'javascript:alert(1)', ['url']] as $url) {
            $this->assertFalse(SafeRedirect::allows($url));
        }
        $this->assertTrue(SafeRedirect::allows('/account?tab=devices'));
        $this->assertTrue(SafeRedirect::allows('https://example.com/account'));
    }

    public function test_cache_headers_preserve_existing_vary_fields(): void
    {
        $response = app(NoCache::class)->handle(Request::create('/'), fn () => response('ok')->setVary('Origin'));
        $this->assertContains('Origin', $response->getVary());
        $this->assertContains('Cookie', $response->getVary());
        $this->assertContains('Authorization', $response->getVary());
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_sensitive_pages_are_not_indexable_and_tokens_are_not_referred(): void
    {
        config(['security.indexable' => true]);
        foreach (['/account', '/admin/dashboard', '/invoices/magic?token=secret'] as $url) {
            $response = app(SecurityHeaders::class)->handle(Request::create($url), fn () => response('ok'));
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        }
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $public = app(SecurityHeaders::class)->handle(Request::create('/workshops'), fn () => response('ok'));
        $this->assertFalse($public->headers->has('X-Robots-Tag'));
    }

    public function test_bare_checkbox_preserves_bindings_without_nested_labels(): void
    {
        $html = Blade::render('<x-ui.checkbox bare small name="items[]" value="abc" x-bind:checked="selected" x-on:change="toggle" aria-label="Select item" />');
        $this->assertStringNotContainsString('<label', $html);
        foreach (['type="checkbox"', 'name="items[]"', 'value="abc"', 'x-bind:checked="selected"', 'x-on:change="toggle"', 'aria-label="Select item"', 'sm-checkbox-small'] as $attribute) {
            $this->assertStringContainsString($attribute, $html);
        }
        $this->assertStringNotContainsString('id="items[]"', $html);
    }

    public function test_embedded_controls_preserve_form_values_and_button_attributes(): void
    {
        $html = Blade::render('<x-ui.input-control type="number" name="quantity" value="2" x-bind:max="stock" aria-label="Quantity" /><x-ui.select-control name="state"><option value="QLD" selected>Queensland</option></x-ui.select-control><x-ui.button variant="plain" type="submit" :button-attributes="$bag">Continue</x-ui.button>', [
            'bag' => new ComponentAttributeBag(['form' => 'checkout', 'x-bind:disabled' => 'busy']),
        ]);
        foreach (['name="quantity"', 'value="2"', 'x-bind:max="stock"', 'name="state"', 'value="QLD" selected', 'type="submit"', 'form="checkout"', 'x-bind:disabled="busy"'] as $attribute) {
            $this->assertStringContainsString($attribute, $html);
        }
    }

    public function test_select_help_text_is_not_evaluated_as_javascript(): void
    {
        $html = Blade::render('<x-ui.select error="" name="facilitator" label="Facilitator" info="Workshop task reminders go here." />');
        $this->assertStringContainsString('Workshop task reminders go here.', $html);
        $this->assertStringNotContainsString('x-text="Workshop task', $html);
        $dynamic = Blade::render('<x-ui.select error="" name="visibility" label="Visibility" info-expression="visibilityHelp" />');
        $this->assertStringContainsString('x-text="visibilityHelp"', $dynamic);
    }
}
