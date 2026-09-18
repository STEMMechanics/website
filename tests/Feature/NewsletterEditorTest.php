<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\Location;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\NewsletterProductSelectionService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_hiding_a_workshop_advances_the_selection_in_editor_dashboard_and_email_and_can_be_restored(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $location = Location::factory()->create(['name' => 'Julia Creek Library']);
        $media = Media::query()->create(['name' => 'newsletter-test.png', 'title' => 'Newsletter', 'hash' => str_repeat('n', 64), 'mime_type' => 'image/png', 'size' => 100, 'user_id' => $admin->id]);
        $workshops = collect(range(1, 8))->map(fn (int $day) => Workshop::factory()->create([
            'title' => 'Newsletter workshop '.$day, 'starts_at' => now()->addDays($day),
            'location_id' => $location->id, 'user_id' => $admin->id, 'hero_media_name' => $media->name,
        ]));
        $selector = app(NewsletterProductSelectionService::class);
        $snapshot = $selector->selection();
        $this->get(route('admin.newsletter.index'))->assertOk()
            ->assertSee('Julia Creek Library')->assertViewHas('newsletterWorkshops', fn ($items) => $items->modelKeys() === $workshops->take(6)->pluck('id')->all());

        $this->put(route('admin.newsletter.workshops.update'), ['workshop_id' => $workshops[0]->id, 'action' => 'hide'])
            ->assertRedirect(route('admin.newsletter.index'));
        $expectedIds = $workshops->slice(1, 6)->pluck('id')->all();
        $this->get(route('admin.newsletter.index'))->assertOk()
            ->assertViewHas('newsletterWorkshops', fn ($items) => $items->modelKeys() === $expectedIds)
            ->assertViewHas('hiddenNewsletterWorkshops', fn ($items) => $items->modelKeys() === [$workshops[0]->id]);
        $this->assertSame($expectedIds, (new UpcomingWorkshops('test@example.com'))->workshops->modelKeys());
        $this->assertSame($workshops->take(6)->pluck('id')->all(), (new UpcomingWorkshops('snapshot@example.com', storeSelection: $snapshot))->workshops->modelKeys());
        $this->post(route('admin.subscription.send-test-now'), ['test_email' => 'preview@example.com'])->assertRedirect();
        Queue::assertPushed(SendEmail::class, fn ($job) => $job->mailable instanceof UpcomingWorkshops && $job->mailable->workshops->modelKeys() === $expectedIds);
        $workplan = app(WeeklyWorkplanService::class)->build();
        $this->assertSame($expectedIds, $workplan['newsletter']['workshops']->pluck('id')->all());
        $html = view('admin.dashboard.partials.weekly-workplan', compact('workplan'))->render();
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.newsletter.index'), '/').'"[^>]*>.*?Review newsletter/s', $html);
        $this->assertFalse((bool) $workshops[0]->fresh()->is_hidden);

        $this->put(route('admin.newsletter.workshops.update'), ['workshop_id' => $workshops[0]->id, 'action' => 'restore'])->assertRedirect();
        $this->assertSame($workshops->take(6)->pluck('id')->all(), (new UpcomingWorkshops('test@example.com'))->workshops->modelKeys());
    }

    public function test_workshop_controls_require_an_admin_and_validate_the_workshop(): void
    {
        $this->put(route('admin.newsletter.workshops.update'), ['workshop_id' => 'missing', 'action' => 'hide'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->put(route('admin.newsletter.workshops.update'), ['workshop_id' => 'missing', 'action' => 'hide'])->assertForbidden();
        $this->admin();
        $this->put(route('admin.newsletter.workshops.update'), ['workshop_id' => 'missing', 'action' => 'hide'])->assertSessionHasErrors('workshop_id');
    }

    public function test_sparse_theme_can_be_explicitly_filled_from_its_categories_and_preserves_locks(): void
    {
        $this->admin();
        $kits = ProductCategory::factory()->create(['slug' => 'kits']);
        $materials = ProductCategory::factory()->create(['slug' => 'materials']);
        ProductCategory::factory()->create(['slug' => 'parts']);
        $recent = Product::factory()->create(['title' => 'Recent kit', 'inventory_quantity' => 10]);
        $recent->categories()->attach($kits);
        $older = Product::factory()->count(3)->create(['created_at' => now()->subMonth(), 'inventory_quantity' => 10]);
        $older->each(fn ($product) => $product->categories()->attach($kits));
        $nonKit = Product::factory()->create(['title' => 'A material', 'inventory_quantity' => 10]);
        $nonKit->categories()->attach($materials);
        $soldOut = Product::factory()->create(['inventory_quantity' => 0]);
        $soldOut->categories()->attach($kits);
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $this->assertSame([$recent->id], $draft->sections[0]['product_ids']);
        $sections = $draft->sections;
        $sections[0]['locked_product_ids'] = [$recent->id];
        $this->get(route('admin.newsletter.index'))->assertOk()->assertSee('Available products matching this theme: 1.')
            ->assertViewHas('storeProductsBySection', fn ($items) => $items[0]->contains($recent) && $items[0]->contains($older[0]) && ! $items[0]->contains($nonKit) && ! $items[0]->contains($soldOut));
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $sections, 'fill_empty_slots' => 0])->assertRedirect();
        $updated = $draft->fresh()->sections[0];
        $this->assertCount(3, $updated['product_ids']);
        $this->assertSame([$recent->id], $updated['locked_product_ids']);
        $this->assertSame($recent->id, $updated['product_ids'][0]);
        $this->assertNotContains($nonKit->id, $updated['product_ids']);
        $this->assertNotContains($soldOut->id, $updated['product_ids']);
    }

    public function test_refreshing_an_empty_slot_selects_an_available_matching_product(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'kits']);
        $first = Product::factory()->create(['inventory_quantity' => 10]);
        $first->categories()->attach($category);
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $next = Product::factory()->create(['inventory_quantity' => 10]);
        $next->categories()->attach($category);
        $updated = $selector->refreshProduct($draft, 0, 1);
        $this->assertSame([$first->id, $next->id], $updated->sections[0]['product_ids']);
    }

    public function test_available_products_are_not_lost_behind_sixty_unavailable_products(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'kits']);
        $available = Product::factory()->create(['inventory_quantity' => 10, 'created_at' => now()->subDays(2)]);
        $available->categories()->attach($category);
        Product::factory()->count(60)->create(['inventory_quantity' => 0])->each(fn ($product) => $product->categories()->attach($category));
        $this->assertSame([$available->id], app(NewsletterProductSelectionService::class)->draft()->sections[0]['product_ids']);
    }
}
