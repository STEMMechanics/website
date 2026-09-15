<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\ProductRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_prioritise_shared_categories_and_exclude_unavailable_products(): void
    {
        $categories = ProductCategory::factory()->count(2)->create();
        $source = Product::factory()->create();
        $source->categories()->attach($categories->modelKeys());
        $featured = Product::factory()->create(['is_featured' => true]);
        $featured->categories()->attach($categories->first());
        $closest = Product::factory()->create();
        $closest->categories()->attach($categories->modelKeys());
        $variantOnly = Product::factory()->create(['inventory_quantity' => 0]);
        $variantOnly->categories()->attach($categories->first());
        ProductVariant::factory()->create(['product_id' => $variantOnly->id, 'inventory_quantity' => 2]);
        foreach ([['status' => Product::STATUS_DRAFT], ['status' => Product::STATUS_ARCHIVED], ['inventory_quantity' => 0]] as $attributes) {
            $excluded = Product::factory()->create($attributes);
            $excluded->categories()->attach($categories->modelKeys());
        }
        Product::factory()->create(['is_featured' => true]);
        $fourth = Product::factory()->create();
        $fourth->categories()->attach($categories->first());

        $this->assertSame([$closest->id, $featured->id, $variantOnly->id], app(ProductRecommendationService::class)->forProduct($source)->pluck('id')->all());
        $this->get(route('shop.product.show', $source))->assertOk()
            ->assertSee('Other products you may like')
            ->assertSee(route('shop.product.show', $closest), false)
            ->assertViewHas('recommendedProducts', fn ($products) => $products->count() === 3);
    }

    public function test_no_recommendations_are_shown_without_related_products(): void
    {
        $source = Product::factory()->create();
        Product::factory()->create();
        $this->get(route('shop.product.show', $source))->assertOk()
            ->assertDontSee('Other products you may like');
        $source->categories()->attach(ProductCategory::factory()->create());
        $this->assertTrue(app(ProductRecommendationService::class)->forProduct($source->fresh())->isEmpty());
    }
}
