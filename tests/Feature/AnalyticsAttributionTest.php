<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_touch_attribution_is_captured_and_retained_for_the_session(): void
    {
        $this->withHeaders([
            'Referer' => 'https://www.facebook.com/some-post',
            'User-Agent' => 'Mozilla/5.0 Test Browser',
        ])->get('/?utm_source=newsletter&utm_medium=email&utm_campaign=term-3&utm_term=robotics&utm_content=hero-button')
            ->assertOk();

        $this->withHeaders([
            'Referer' => url('/'),
            'User-Agent' => 'Mozilla/5.0 Test Browser',
        ])->get('/contact?utm_source=should-not-replace-first-touch')
            ->assertOk();

        $events = AnalyticsEvent::query()->orderBy('id')->get();

        $this->assertCount(2, $events);
        $this->assertTrue($events[0]->is_session_entry);
        $this->assertFalse($events[1]->is_session_entry);
        $this->assertSame('/', $events[0]->landing_path);
        $this->assertSame('/', $events[1]->landing_path);
        $this->assertSame('www.facebook.com', $events[0]->referrer_host);
        $this->assertSame('newsletter', $events[0]->acquisition_source);
        $this->assertSame('newsletter', $events[1]->acquisition_source);
        $this->assertSame('newsletter', $events[0]->utm_source);
        $this->assertSame('email', $events[0]->utm_medium);
        $this->assertSame('term-3', $events[0]->utm_campaign);
        $this->assertSame('robotics', $events[0]->utm_term);
        $this->assertSame('hero-button', $events[0]->utm_content);
        $this->assertSame($events[0]->session_token, $events[1]->session_token);
    }

    public function test_external_referrer_is_used_when_no_utm_source_is_present(): void
    {
        $this->withHeaders([
            'Referer' => 'https://www.google.com/search?q=stem',
            'User-Agent' => 'Mozilla/5.0 Test Browser',
        ])->get('/contact')->assertOk();

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame('www.google.com', $event->acquisition_source);
        $this->assertNull($event->utm_source);
        $this->assertSame('/contact', $event->landing_path);
    }

    public function test_stemmechanics_domains_and_subdomains_are_internal_sources(): void
    {
        $this->withHeaders([
            'Referer' => 'https://www.stemmechanics.com.au/workshops',
            'User-Agent' => 'Mozilla/5.0 Test Browser',
        ])->get('/contact')->assertOk();

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame('www.stemmechanics.com.au', $event->referrer_host);
        $this->assertSame('Direct / unknown', $event->acquisition_source);
        $this->assertNull($event->utm_source);
    }

    public function test_redirect_domains_are_internal_sources(): void
    {
        $this->assertContains('stemmechanics.com', config('analytics.internal_referrer_hosts'));
        $this->assertContains('stemmechanics.net', config('analytics.internal_referrer_hosts'));

        $this->withHeaders([
            'Referer' => 'https://offers.stemmechanics.xyz/workshops',
            'User-Agent' => 'Mozilla/5.0 Test Browser',
        ])->get('/contact')->assertOk();

        $event = AnalyticsEvent::query()->sole();

        $this->assertSame('offers.stemmechanics.xyz', $event->referrer_host);
        $this->assertSame('Direct / unknown', $event->acquisition_source);
    }
}
