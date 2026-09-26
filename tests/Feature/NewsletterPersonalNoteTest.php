<?php

namespace Tests\Feature;

use App\Mail\UpcomingWorkshops;
use App\Models\Location;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\NewsletterProductSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterPersonalNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        foreach (['kits', 'materials', 'parts'] as $slug) {
            $category = ProductCategory::factory()->create(['slug' => $slug]);
            Product::factory()->create()->categories()->attach($category);
        }
    }

    public function test_message_is_included_when_it_contains_text_and_is_snapshotted_before_reset(): void
    {
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $body = "A little update from me.\n\nWe have been making <b>new things</b>.";
        $this->put(route('admin.subscription.store-promotion.update'), [
            'sections' => $draft->sections,
            'personal_note' => ['body' => $body],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $snapshot = $selector->selection();
        $this->assertSame($body, $snapshot['personal_note']['body']);
        $this->assertTrue($snapshot['personal_note']['enabled']);
        $html = (new UpcomingWorkshops('test@example.com', storeSelection: $snapshot))->render();
        $this->assertStringContainsString('A little update from me.', $html);
        $this->assertStringContainsString('&lt;b&gt;new things&lt;/b&gt;', $html);
        $this->assertLessThan(strpos($html, 'newsletter-workshop-card__table'), strpos($html, 'data-newsletter-personal-note'));
        $this->assertGreaterThan(strpos($html, 'newsletter-hero__table'), strpos($html, 'data-newsletter-personal-note'));
        $this->get(route('admin.newsletter.index'))->assertOk()->assertSee('Edit newsletter introduction')->assertSee('A little update from me.');
        $selector->clearPresentation($draft->fresh());
        $this->assertFalse($selector->selection()['personal_note']['enabled']);
        $this->assertStringContainsString('A little update from me.', (new UpcomingWorkshops('test@example.com', storeSelection: $snapshot))->render());
    }

    public function test_nonempty_message_is_included_even_if_a_legacy_enabled_flag_is_false_and_blank_message_is_omitted(): void
    {
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections, 'personal_note' => ['format' => 'html', 'body' => '<p><br></p>']])->assertSessionHasNoErrors();
        $this->assertFalse($selector->selection()['personal_note']['enabled']);
        $this->assertStringNotContainsString('newsletter-personal-note', (new UpcomingWorkshops('test@example.com'))->render());
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections, 'personal_note' => ['enabled' => 0, 'body' => 'Keep this draft']])->assertSessionHasNoErrors();
        $this->assertTrue($selector->selection()['personal_note']['enabled']);
        $this->assertStringContainsString('Keep this draft', (new UpcomingWorkshops('test@example.com'))->render());
        $this->assertSame('Keep this draft', $draft->fresh()->personal_note['body']);
    }

    public function test_photo_must_be_a_public_passwordless_image_and_is_preserved_when_other_sections_save(): void
    {
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $photo = Media::create(['name' => 'note-portrait.jpg', 'title' => 'Portrait', 'hash' => str_repeat('a', 64), 'mime_type' => 'image/jpeg', 'size' => 100, 'visibility' => 'public', 'user_id' => auth()->id()]);
        $payload = ['sections' => $draft->sections, 'personal_note' => ['enabled' => 1, 'body' => 'Hello from me.', 'image_name' => $photo->name]];
        $this->put(route('admin.subscription.store-promotion.update'), $payload)->assertSessionHasNoErrors();
        $this->assertNotNull($selector->selection()['personal_note']['image_url']);
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections])->assertSessionHasNoErrors();
        $this->assertSame($photo->name, $draft->fresh()->personal_note['image_name']);
        foreach ([['visibility' => 'private'], ['visibility' => 'public', 'password' => 'secret'], ['password' => null, 'mime_type' => 'application/pdf']] as $attributes) {
            $photo->update($attributes);
            $this->put(route('admin.subscription.store-promotion.update'), $payload)->assertSessionHasErrors('personal_note.image_name');
            $this->assertNull($selector->selection()['personal_note']['image_url']);
        }
    }

    public function test_rich_text_preserves_formatting_and_links_but_removes_unsafe_markup(): void
    {
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $html = '<p>Hello <strong>makers</strong> and <em>friends</em>.</p><p><a href="/shop">Our store</a> or <a href="https://example.com/projects">projects</a>.</p><script>alert(1)</script><a href="javascript:alert(1)">Unsafe</a><img src="x" onerror="alert(2)">';
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections, 'personal_note' => ['enabled' => 1, 'format' => 'html', 'body' => $html]])->assertSessionHasNoErrors();
        $saved = $draft->fresh()->personal_note['body'];
        $this->assertStringContainsString('<strong>makers</strong>', $saved);
        $this->assertStringContainsString('<em>friends</em>', $saved);
        $this->assertStringContainsString('href="'.url('/shop').'"', $saved);
        $this->assertStringContainsString('href="https://example.com/projects"', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('<img', $saved);
        $email = (new UpcomingWorkshops('test@example.com'))->render();
        $this->assertMatchesRegularExpression('/<strong[^>]*>makers<\/strong>/', $email);
        $this->assertStringContainsString('https://example.com/projects', $email);
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections, 'personal_note' => ['format' => 'html', 'body' => '<p><br></p>']])->assertSessionHasNoErrors();
    }

    public function test_header_image_can_be_changed_preserved_and_reset_to_default(): void
    {
        $selector = app(NewsletterProductSelectionService::class);
        $draft = $selector->draft();
        $photo = Media::create(['name' => 'custom-header.jpg', 'title' => 'Header', 'hash' => str_repeat('d', 64), 'mime_type' => 'image/jpeg', 'size' => 100, 'visibility' => 'public', 'user_id' => auth()->id()]);
        $payload = ['sections' => $draft->sections, 'hero_image_name' => $photo->name];
        $this->put(route('admin.subscription.store-promotion.update'), $payload)->assertSessionHasNoErrors();
        $snapshot = $selector->selection();
        $this->assertSame($photo->name, $snapshot['hero_image_name']);
        $this->assertStringContainsString($snapshot['hero_image_url'], (new UpcomingWorkshops('test@example.com', storeSelection: $snapshot))->render());
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections])->assertSessionHasNoErrors();
        $this->assertSame($photo->name, $draft->fresh()->hero_image_name);
        $photo->update(['visibility' => 'private']);
        $this->put(route('admin.subscription.store-promotion.update'), $payload)->assertSessionHasErrors('hero_image_name');
        $this->assertNull($selector->selection()['hero_image_url']);
        $this->put(route('admin.subscription.store-promotion.update'), ['sections' => $draft->sections, 'hero_image_name' => ''])->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->hero_image_name);
        $this->assertNull($selector->selection()['hero_image_url']);
    }

    public function test_link_picker_offers_store_items_and_public_workshops(): void
    {
        Location::factory()->create();
        Media::create(['name' => 'workshop.jpg', 'title' => 'Workshop', 'hash' => str_repeat('e', 64), 'mime_type' => 'image/jpeg', 'size' => 100, 'visibility' => 'public', 'user_id' => auth()->id()]);
        $public = Workshop::factory()->create(['hero_media_name' => 'workshop.jpg', 'user_id' => auth()->id(), 'title' => 'Public making workshop', 'starts_at' => now()->addDays(5), 'is_private' => false]);
        $private = Workshop::factory()->create(['hero_media_name' => 'workshop.jpg', 'user_id' => auth()->id(), 'title' => 'Private group workshop', 'starts_at' => now()->addDays(5), 'is_private' => true]);
        $response = $this->get(route('admin.newsletter.index'))->assertOk();
        $links = $response->viewData('newsletterLinkOptions');
        $this->assertTrue($links->contains('url', route('workshop.show', $public)));
        $this->assertFalse($links->contains('url', route('workshop.show', $private)));
        $this->assertTrue($links->contains('url', route('shop.product.show', Product::first())));
    }
}
