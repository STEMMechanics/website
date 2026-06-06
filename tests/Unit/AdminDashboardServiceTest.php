<?php

namespace Tests\Unit;

use App\Models\AnalyticsEvent;
use App\Models\EmailSubscriptions;
use App\Models\Expense;
use App\Models\Location;
use App\Models\Media;
use App\Models\Product;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Models\WorkshopInterest;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_service_builds_summary_for_selected_period(): void
    {
        Carbon::setTestNow('2026-05-28 12:00:00');

        $admin = $this->createAdminUser();
        $previousUser = User::factory()->create([
            'firstname' => 'Previous',
            'surname' => 'User',
        ]);

        $previousWorkshop = $this->createWorkshop([
            'title' => 'Previous Workshop',
            'registration' => 'tickets',
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHours(2),
            'closes_at' => Carbon::now()->addHours(18),
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $previousUser->id,
            'workshop_id' => $previousWorkshop->id,
            'firstname' => 'Prev',
            'surname' => 'Ticket',
            'email' => 'prev-ticket@example.com',
            'phone' => '0400111000',
            'is_early_bird' => false,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => $previousUser->id,
            'created_by' => $admin->id,
            'received_on' => Carbon::now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 60.00,
            'gst_amount' => 5.45,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_REFUND,
            'user_id' => $previousUser->id,
            'created_by' => $admin->id,
            'received_on' => Carbon::now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 10.00,
            'gst_amount' => 0.91,
        ]);

        Expense::query()->create([
            'created_by' => $admin->id,
            'supplier' => 'Previous Supplier',
            'description' => 'Previous period expense',
            'paid_on' => Carbon::now()->toDateString(),
            'total_amount' => 20.00,
            'gst_amount' => 1.82,
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'prev-session',
            'visitor_hash' => 'prev-visitor',
            'path' => '/workshops',
            'route_name' => 'workshop.index',
            'workshop_id' => $previousWorkshop->id,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);

        WorkshopInterest::query()->create([
            'workshop_id' => $previousWorkshop->id,
            'user_id' => $previousUser->id,
            'name' => 'Previous Interest',
            'email' => 'prev-interest@example.com',
            'phone' => '0400999000',
        ]);

        EmailSubscriptions::query()->create([
            'email' => 'prev-sub@example.com',
            'confirmed' => Carbon::now(),
        ]);

        Carbon::setTestNow('2026-06-06 12:00:00');

        $currentUser = User::factory()->create([
            'firstname' => 'Current',
            'surname' => 'User',
        ]);

        $currentWorkshop = $this->createWorkshop([
            'title' => 'Current Workshop',
            'registration' => 'tickets',
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->subDay()->addHours(2),
            'closes_at' => Carbon::now()->subDays(2),
        ]);

        $currentExternalWorkshop = $this->createWorkshop([
            'title' => 'External Workshop',
            'registration' => 'link',
            'registration_data' => 'https://example.com/tickets',
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->subDay()->addHours(2),
            'closes_at' => Carbon::now()->subDays(2),
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $currentUser->id,
            'workshop_id' => $currentWorkshop->id,
            'firstname' => 'Alice',
            'surname' => 'Early',
            'email' => 'alice@example.com',
            'phone' => '0400222000',
            'is_early_bird' => true,
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $currentUser->id,
            'workshop_id' => $currentWorkshop->id,
            'firstname' => 'Bob',
            'surname' => 'Regular',
            'email' => 'bob@example.com',
            'phone' => '0400333000',
            'is_early_bird' => false,
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PAID,
            'user_id' => $currentUser->id,
            'workshop_id' => $currentWorkshop->id,
            'firstname' => 'Carol',
            'surname' => 'Regular',
            'email' => 'carol@example.com',
            'phone' => '0400444000',
            'is_early_bird' => false,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => $currentUser->id,
            'created_by' => $admin->id,
            'received_on' => Carbon::now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 120.00,
            'gst_amount' => 10.91,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_REFUND,
            'user_id' => $currentUser->id,
            'created_by' => $admin->id,
            'received_on' => Carbon::now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 15.00,
            'gst_amount' => 1.36,
        ]);

        Expense::query()->create([
            'created_by' => $admin->id,
            'supplier' => 'Current Supplier',
            'description' => 'Current period expense',
            'paid_on' => Carbon::now()->toDateString(),
            'total_amount' => 40.00,
            'gst_amount' => 3.64,
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-a',
            'visitor_hash' => 'current-visitor-a',
            'path' => '/workshops',
            'route_name' => 'workshop.index',
            'workshop_id' => $currentWorkshop->id,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-a',
            'visitor_hash' => 'current-visitor-a',
            'path' => '/workshops/'.$currentWorkshop->slug,
            'route_name' => 'workshop.show',
            'workshop_id' => $currentWorkshop->id,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_SEARCH,
            'session_token' => 'current-session-b',
            'visitor_hash' => 'current-visitor-b',
            'path' => '/search',
            'route_name' => 'search.index',
            'workshop_id' => null,
            'search_term' => 'robotics',
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);

        WorkshopInterest::query()->create([
            'workshop_id' => $currentWorkshop->id,
            'user_id' => $currentUser->id,
            'name' => 'Current Interest',
            'email' => 'current-interest@example.com',
            'phone' => '0400888000',
        ]);

        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-b',
            'visitor_hash' => 'current-visitor-b',
            'path' => '/store',
            'route_name' => 'shop.index',
            'workshop_id' => null,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        $currentStoreProductOne = Product::factory()->create([
            'title' => 'Store Item One',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $currentStoreProductTwo = Product::factory()->create([
            'title' => 'Store Item Two',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $currentStoreOrder = StoreOrder::factory()->create([
            'status' => StoreOrder::STATUS_PROCESSING,
            'paid_at' => Carbon::now(),
            'user_id' => $currentUser->id,
        ]);
        StoreOrderItem::factory()->create([
            'store_order_id' => $currentStoreOrder->id,
            'product_id' => $currentStoreProductOne->id,
            'product_title' => 'Store Item One',
            'product_slug' => $currentStoreProductOne->slug,
            'quantity' => 3,
            'available_now_quantity' => 3,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);
        StoreOrderItem::factory()->create([
            'store_order_id' => $currentStoreOrder->id,
            'product_id' => $currentStoreProductTwo->id,
            'product_title' => 'Store Item Two',
            'product_slug' => $currentStoreProductTwo->slug,
            'quantity' => 2,
            'available_now_quantity' => 2,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-c',
            'visitor_hash' => 'current-visitor-c',
            'path' => route('shop.product.show', $currentStoreProductOne, false),
            'route_name' => 'shop.product.show',
            'workshop_id' => null,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-d',
            'visitor_hash' => 'current-visitor-d',
            'path' => route('shop.product.show', $currentStoreProductTwo, false),
            'route_name' => 'shop.product.show',
            'workshop_id' => null,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-b',
            'visitor_hash' => 'current-visitor-b',
            'path' => route('workshop.show', $currentExternalWorkshop, false),
            'route_name' => 'workshop.show',
            'workshop_id' => $currentExternalWorkshop->id,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);
        AnalyticsEvent::query()->create([
            'event_type' => AnalyticsEvent::TYPE_PAGE_VIEW,
            'session_token' => 'current-session-b',
            'visitor_hash' => 'current-visitor-b',
            'path' => '/contact',
            'route_name' => 'contact',
            'workshop_id' => null,
            'search_term' => null,
            'referrer_host' => null,
            'http_method' => 'GET',
            'created_at' => Carbon::now(),
        ]);

        EmailSubscriptions::query()->create([
            'email' => 'current-sub@example.com',
            'confirmed' => Carbon::now(),
        ]);

        Carbon::setTestNow('2026-06-06 12:00:01');

        $data = app(AdminDashboardService::class)->build('week');

        $this->assertSame('week', $data['period']);
        $this->assertSame('This week', $data['periodLabel']);
        $this->assertSame(['Workshops', 'Tickets', 'Store', 'Finance', 'Website', 'Growth'], collect($data['cards'])->pluck('title')->all());

        $workshops = $this->cardByTitle($data, 'Workshops');
        $tickets = $this->cardByTitle($data, 'Tickets');
        $store = $this->cardByTitle($data, 'Store');
        $finance = $this->cardByTitle($data, 'Finance');
        $website = $this->cardByTitle($data, 'Website');
        $growth = $this->cardByTitle($data, 'Growth');

        $this->assertSame('3', $this->metricByLabel($workshops, 'Workshop views')['current']);

        $this->assertSame('3', $this->metricByLabel($tickets, 'Tickets sold')['current']);

        $this->assertSame('1', $this->metricByLabel($store, 'Store views')['current']);
        $this->assertSame('2', $this->metricByLabel($store, 'Product views')['current']);
        $this->assertSame('5', $this->metricByLabel($store, 'Items sold')['current']);

        $this->assertSame('$65.00', $this->metricByLabel($finance, 'Profit')['current']);
        $this->assertSame('$120.00', $this->metricByLabel($finance, 'Income')['current']);
        $this->assertSame('$15.00', $this->metricByLabel($finance, 'Refunds')['current']);
        $this->assertSame('$40.00', $this->metricByLabel($finance, 'Expenses')['current']);

        $this->assertSame('8', $this->metricByLabel($website, 'Page views')['current']);
        $this->assertSame('4', $this->metricByLabel($website, 'Unique visitors')['current']);

        $this->assertSame('3', $this->metricByLabel($growth, 'New users')['current']);
        $this->assertSame('1', $this->metricByLabel($growth, 'New subscriptions')['current']);
        $this->assertCount(2, $data['workshopSalesRows']);
        $this->assertSame('Current Workshop', $data['workshopSalesRows'][0]['workshop_title']);
        $this->assertSame(1, $data['workshopSalesRows'][0]['views']);
        $this->assertSame(3, $data['workshopSalesRows'][0]['tickets_sold']);
        $this->assertSame('External Workshop', $data['workshopSalesRows'][1]['workshop_title']);
        $this->assertSame(1, $data['workshopSalesRows'][1]['views']);
        $this->assertSame(0, $data['workshopSalesRows'][1]['tickets_sold']);
        $this->assertCount(2, $data['storeSalesRows']);
        $this->assertSame('Store Item One', $data['storeSalesRows'][0]['product_title']);
        $this->assertSame(1, $data['storeSalesRows'][0]['views']);
        $this->assertSame(3, $data['storeSalesRows'][0]['items_sold']);
        $this->assertSame('Store Item Two', $data['storeSalesRows'][1]['product_title']);
        $this->assertSame(1, $data['storeSalesRows'][1]['views']);
        $this->assertSame(2, $data['storeSalesRows'][1]['items_sold']);

        Carbon::setTestNow();
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createWorkshop(array $overrides = []): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.Str::lower(bin2hex(random_bytes(4))).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create(array_merge([
            'title' => 'Dashboard Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(5)->addHours(2),
            'publish_at' => Carbon::now()->subDay(),
            'closes_at' => Carbon::now()->addDays(4),
            'status' => 'open',
            'registration' => 'none',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cardByTitle(array $data, string $title): array
    {
        $card = collect($data['cards'])->firstWhere('title', $title);

        $this->assertIsArray($card);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function metricByLabel(array $card, string $label): array
    {
        $metric = collect($card['metrics'])->firstWhere('label', $label);

        $this->assertIsArray($metric);

        return $metric;
    }
}
