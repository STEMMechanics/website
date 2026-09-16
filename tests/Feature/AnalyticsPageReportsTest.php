<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalyticsController;
use App\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsPageReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_reports_exclude_tracking_events_but_preserve_recommendation_statistics(): void
    {
        // The production hourly report uses MySQL date formatting.
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($date, $format) => date('Y-m-d H:00:00', strtotime($date)), 2);

        foreach ([
            [AnalyticsEvent::TYPE_PAGE_VIEW, '/about'],
            [AnalyticsEvent::TYPE_SEARCH, '/search'],
            [AnalyticsEvent::TYPE_RECOMMENDATION_IMPRESSION, '/workshops/recommendations/impression'],
            [AnalyticsEvent::TYPE_RECOMMENDATION_CLICK, '/workshops/recommendations/click'],
            [AnalyticsEvent::TYPE_REGISTRATION_CLICK, '/workshops/example/register'],
        ] as [$type, $path]) {
            AnalyticsEvent::factory()->create(['event_type' => $type, 'path' => $path, 'search_term' => null]);
        }

        $data = app(AnalyticsController::class)->index(Request::create('/admin/analytics'))->getData();

        $this->assertSame(2, $data['totals']['views']);
        $this->assertEqualsCanonicalizing(['/about', '/search'], $data['topPages']->pluck('path')->all());
        $this->assertEquals(2, $data['daily']->sum('views'));
        $this->assertEquals(2, $data['activeHours']->sum('views'));
        $this->assertSame(2, $data['landingPages']->total());
        $this->assertSame(2, $data['sessionFlows']->total());
        $this->assertSame(1, $data['recommendationAnalytics']['impressions']);
        $this->assertSame(1, $data['recommendationAnalytics']['clicks']);
    }
}
