<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\EmailSubscriptions;
use App\Models\NewsletterProductPromotion;
use App\Models\NewsletterStoreTheme;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\NewsletterProductSelectionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterStorePromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_next_newsletter_has_two_sections_with_three_category_products_each(): void
    {
        foreach (['kits' => 4, 'materials' => 3, 'parts' => 2] as $slug => $count) {
            $category = ProductCategory::factory()->create(['name' => ucfirst($slug), 'slug' => $slug]);
            Product::factory()->count($count)->create(['inventory_quantity' => 10])->each(
                fn (Product $product) => $product->categories()->attach($category)
            );
        }

        $selection = app(NewsletterProductSelectionService::class)->selection();

        $this->assertSame('draft', $selection['source']);
        $this->assertCount(2, $selection['sections']);
        $this->assertCount(3, $selection['sections'][0]['products']);
        $this->assertCount(3, $selection['sections'][1]['products']);
        $this->assertSame(['kits'], $selection['sections'][0]['category_slugs']);
        $this->assertSame(['materials', 'parts'], $selection['sections'][1]['category_slugs']);
    }

    public function test_admin_can_edit_and_lock_store_sections_that_render_in_the_newsletter(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $category = ProductCategory::factory()->create(['name' => 'Kits', 'slug' => 'kits']);
        $products = Product::factory()->count(3)->create(['inventory_quantity' => 10]);
        $products->each(fn (Product $product) => $product->categories()->attach($category));

        $this->actingAs($admin)->put(route('admin.subscription.store-promotion.update'), [
            'sections' => [
                ['key' => 'kits', 'title' => 'Weekend maker picks', 'intro' => 'Everything you need for the next build.', 'category_slugs' => ['kits'], 'product_ids' => $products->pluck('id')->all(), 'locked_product_ids' => [$products->first()->id]],
                ['key' => 'extras', 'title' => 'Grab extras', 'intro' => '', 'category_slugs' => ['kits'], 'product_ids' => [], 'locked_product_ids' => []],
            ],
        ])->assertRedirect(route('admin.subscription.index'));

        $promotion = NewsletterProductPromotion::query()->firstOrFail();
        $this->assertSame([$products->first()->id], $promotion->sections[0]['locked_product_ids']);

        $selection = app(NewsletterProductSelectionService::class)->selection();
        $this->assertSame('Weekend maker picks', $selection['sections'][0]['title']);

        $rendered = (new UpcomingWorkshops('subscriber@example.com'))->render();
        $this->assertStringContainsString($products->first()->title, $rendered);
        $this->assertStringContainsString('max-width: 780px', $rendered);
        $this->assertStringContainsString('text-align: left', $rendered);
        $this->assertStringContainsString('border-left: 8px solid #16a34a', $rendered);
        $this->assertStringContainsString('height: 220px', $rendered);
        $this->assertStringContainsString('valign="bottom"', $rendered);
        $this->assertStringNotContainsString('&lt;table role=&quot;presentation&quot;', $rendered);
    }

    public function test_bulk_newsletter_uses_one_snapshot_and_clears_locks_after_queueing(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $category = ProductCategory::factory()->create(['name' => 'Kits', 'slug' => 'kits']);
        $product = Product::factory()->create(['inventory_quantity' => 10]);
        $product->categories()->attach($category);
        NewsletterProductPromotion::query()->create([
            'is_active' => true,
            'sections' => [
                ['key' => 'kits', 'title' => 'Locked kits', 'intro' => '', 'category_slugs' => ['kits'], 'product_ids' => [$product->id], 'locked_product_ids' => [$product->id]],
                ['key' => 'extras', 'title' => 'Extras', 'intro' => '', 'category_slugs' => ['kits'], 'product_ids' => [], 'locked_product_ids' => []],
            ],
        ]);
        EmailSubscriptions::query()->create(['email' => 'one@example.com', 'confirmed' => now()]);
        EmailSubscriptions::query()->create(['email' => 'two@example.com', 'confirmed' => now()]);

        $this->actingAs($admin)->post(route('admin.subscription.send-all-now'))->assertRedirect();

        Queue::assertPushed(SendEmail::class, 2);
        $this->assertSame([], NewsletterProductPromotion::query()->firstOrFail()->sections[0]['locked_product_ids']);
    }

    public function test_admin_can_refresh_copy_independently_of_products(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        foreach (['kits', 'materials', 'parts'] as $slug) {
            ProductCategory::factory()->create(['name' => ucfirst($slug), 'slug' => $slug]);
        }

        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $originalProductIds = $draft->sections[0]['product_ids'];

        $this->actingAs($admin)->put(route('admin.subscription.store-promotion.update'), [
            'sections' => $draft->sections,
            'refresh_copy' => 0,
        ])->assertRedirect(route('admin.subscription.index'));

        $updated = $draft->fresh();
        $this->assertSame('Recently updated kits', $updated->sections[0]['title']);
        $this->assertSame($originalProductIds, $updated->sections[0]['product_ids']);
        $this->assertSame(['materials', 'parts'], $updated->sections[1]['category_slugs']);
    }

    public function test_theme_change_rebuilds_copy_and_products_and_a_single_product_can_be_refreshed(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $category = ProductCategory::factory()->create(['name' => 'Kits', 'slug' => 'kits']);
        $products = Product::factory()->count(5)->sequence(
            ['title' => 'Kit One'],
            ['title' => 'Kit Two'],
            ['title' => 'Kit Three'],
            ['title' => 'Kit Four'],
            ['title' => 'Kit Five'],
        )->create(['inventory_quantity' => 10, 'restocked_at' => now()]);
        $products->each(fn (Product $product) => $product->categories()->attach($category));
        foreach (['materials', 'parts'] as $slug) {
            ProductCategory::factory()->create(['name' => ucfirst($slug), 'slug' => $slug]);
        }

        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $sections = $draft->sections;
        $backInStock = NewsletterStoreTheme::query()->where('name', 'Back in Stock')->firstOrFail();
        $sections[0]['theme'] = 'managed';
        $sections[0]['theme_id'] = $backInStock->id;

        $this->actingAs($admin)->put(route('admin.subscription.store-promotion.update'), [
            'sections' => $sections,
            'apply_theme' => 0,
        ])->assertRedirect(route('admin.subscription.index'));

        $themed = $draft->fresh();
        $this->assertSame($backInStock->id, $themed->sections[0]['theme_id']);
        $this->assertSame('Back in stock', $themed->sections[0]['title']);
        $this->assertSame('Popular workshop favourites are available again.', $themed->sections[0]['intro']);
        $this->assertSame(['kits', 'materials', 'parts'], $themed->sections[0]['category_slugs']);
        $beforeRefresh = $themed->sections[0]['product_ids'];

        $requestSections = $themed->sections;
        $requestSections[0]['product_titles'] = collect($beforeRefresh)->map(fn (int $id) => $products->firstWhere('id', $id)?->title)->all();
        $this->actingAs($admin)->put(route('admin.subscription.store-promotion.update'), [
            'sections' => $requestSections,
            'refresh_product' => '0:0',
        ])->assertRedirect(route('admin.subscription.index'));

        $this->assertNotSame($beforeRefresh[0], $draft->fresh()->sections[0]['product_ids'][0]);
    }

    public function test_subscription_theme_control_submits_the_changed_section(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        foreach (['kits', 'materials', 'parts'] as $slug) {
            ProductCategory::factory()->create(['name' => ucfirst($slug), 'slug' => $slug]);
        }

        $this->actingAs($admin)->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertSee('data-theme-mode="0"', false)
            ->assertSee('window.SMNewsletterApplyTheme(this.form, 0, this)', false)
            ->assertSee('formData.set(actionName, String(actionValue))', false)
            ->assertSee("'refresh_product', '0:0'", false)
            ->assertSee("'refresh_section', '0'", false)
            ->assertSee('section.replaceWith(replacement)', false)
            ->assertSee('newsletter-custom-theme-dialog', false)
            ->assertSee('window.SMNewsletterOpenCustom', false)
            ->assertDontSee('label="Categories', false);
    }

    public function test_disabled_section_is_not_rendered_or_automatically_repopulated(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Kits', 'slug' => 'kits']);
        $product = Product::factory()->create(['inventory_quantity' => 10]);
        $product->categories()->attach($category);
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $sections = $draft->sections;
        $sections[0]['theme'] = 'disabled';
        $sections[0]['theme_id'] = null;
        $sections[0]['title'] = 'This must not appear';
        $sections[0]['product_ids'] = [$product->id];
        $selector->saveSections($draft, $sections);

        $selection = $selector->selection();
        $this->assertFalse(collect($selection['sections'])->contains('title', 'This must not appear'));
        $this->assertSame('disabled', $selector->draft()->sections[0]['theme']);
    }

    public function test_theme_with_no_matching_products_preserves_the_existing_section(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        foreach (['kits', 'materials', 'parts'] as $slug) {
            $category = ProductCategory::factory()->create(['name' => ucfirst($slug), 'slug' => $slug]);
            Product::factory()->create(['inventory_quantity' => 10, 'restocked_at' => null])->categories()->attach($category);
        }
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $originalSection = $draft->sections[1];
        $backInStock = NewsletterStoreTheme::query()->where('name', 'Back in Stock')->firstOrFail();
        $sections = $draft->sections;
        $sections[1]['theme'] = 'managed';
        $sections[1]['theme_id'] = $backInStock->id;

        $this->actingAs($admin)->put(route('admin.subscription.store-promotion.update'), [
            'sections' => $sections,
            'apply_theme' => 1,
        ])->assertRedirect(route('admin.subscription.index'))->assertSessionHas('message-type', 'warning');

        $this->assertSame($originalSection, $draft->fresh()->sections[1]);
    }

    public function test_inventory_transition_from_zero_records_a_restock_for_products_and_variants(): void
    {
        $product = Product::factory()->create(['inventory_quantity' => 0]);
        $product->update(['inventory_quantity' => 5]);
        $this->assertNotNull($product->fresh()->restocked_at);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'inventory_quantity' => 0,
        ]);
        $variant->update(['inventory_quantity' => 8]);

        $this->assertNotNull($variant->fresh()->restocked_at);
        $this->assertNotNull($product->fresh()->restocked_at);
    }
}
