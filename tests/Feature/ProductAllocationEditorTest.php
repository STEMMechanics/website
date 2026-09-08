<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\ProductAllocation;
use App\Services\Finance\ProductAllocationEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAllocationEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    private function payload(): array
    {
        return ['title' => 'Tape packs', 'sku' => 'TAPE-PACK', 'slug' => 'tape-packs', 'product_type' => 'physical', 'status' => 'active', 'price' => '2.95', 'base_variant_name' => '2 Pack'];
    }

    private function rules(int $cost): array
    {
        return ['fixed' => [2 => $cost], 'percent' => [5 => 4000, 6 => 6000]];
    }

    public function test_product_save_creates_allocation_for_base_and_new_variants_and_preserves_invoice_snapshots(): void
    {
        $this->admin();
        $payload = $this->payload() + [
            'variants' => [['name' => '10 Pack', 'price' => '12.95', 'sku' => 'TAPE-10']],
            'product_allocations_json' => json_encode(['base' => $this->rules(50), 'variants' => [['inherit' => false, 'rules' => $this->rules(250)]]]),
        ];
        $this->post(route('admin.shop.product.store'), $payload)->assertSessionHasNoErrors();
        $product = Product::where('slug', 'tape-packs')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $service = app(ProductAllocation::class);
        $this->assertSame(50, $service->resolve($product->id)['rules']['fixed'][2]);
        $this->assertSame(250, $service->resolve($product->id, $variant->id)['rules']['fixed'][2]);
        $this->assertSame([], app(ProductAllocationEditor::class)->attentionIds());
        $invoice = Invoice::factory()->create();
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $product->id, 'details_json' => ['variant_id' => $variant->id]]);
        $payload['variants'][0]['id'] = $variant->id;
        $payload['product_allocations_json'] = json_encode(['base' => $this->rules(75), 'variants' => [['inherit' => true, 'rules' => $this->rules(75)]]]);
        $this->put(route('admin.shop.product.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(75, $service->resolve($product->id, $variant->id)['rules']['fixed'][2]);
        $this->assertSame(250, $line->fresh()->product_allocation_snapshot['rules']['fixed'][2]);
    }

    public function test_reordered_and_removed_variants_keep_the_correct_allocations(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $first = ProductVariant::factory()->create(['product_id' => $product->id]);
        $second = ProductVariant::factory()->create(['product_id' => $product->id]);
        $payload = $this->payload() + ['variants' => [
            ['id' => $second->id, 'name' => 'Second', 'sku' => 'SECOND'],
            ['id' => $first->id, 'name' => 'First', 'sku' => 'FIRST'],
        ], 'product_allocations_json' => json_encode(['base' => $this->rules(50), 'variants' => [
            ['inherit' => false, 'rules' => $this->rules(200)], ['inherit' => false, 'rules' => $this->rules(100)],
        ]])];
        $this->put(route('admin.shop.product.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(200, app(ProductAllocation::class)->resolve($product->id, $second->id)['rules']['fixed'][2]);
        $this->assertSame(100, app(ProductAllocation::class)->resolve($product->id, $first->id)['rules']['fixed'][2]);
        $payload['variants'] = [$payload['variants'][0]];
        $payload['product_allocations_json'] = json_encode(['base' => $this->rules(50), 'variants' => [['inherit' => false, 'rules' => $this->rules(200)]]]);
        $this->put(route('admin.shop.product.update', $product->fresh()), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('finance_product_allocations', ['variant_id' => $first->id]);
    }

    public function test_invalid_allocations_and_foreign_variants_do_not_partially_save_product_changes(): void
    {
        $this->admin();
        $product = Product::factory()->create(['title' => 'Original']);
        $payload = $this->payload() + ['product_allocations_json' => json_encode(['base' => ['fixed' => [], 'percent' => [2 => 8000, 6 => 3000]], 'variants' => []])];
        $this->put(route('admin.shop.product.update', $product), $payload)->assertSessionHasErrors('product_allocations_json');
        $this->assertSame('Original', $product->fresh()->title);
        $foreign = ProductVariant::factory()->create();
        $payload['product_allocations_json'] = json_encode(['base' => $this->rules(50), 'variants' => [['inherit' => false, 'rules' => $this->rules(250)]]]);
        $payload['variants'] = [['id' => $foreign->id, 'name' => 'Foreign', 'sku' => 'FOREIGN']];
        $this->put(route('admin.shop.product.update', $product), $payload)->assertSessionHasErrors('variants.0.id');
        $this->assertSame('Original', $product->fresh()->title);
        $this->assertDatabaseCount('finance_product_allocations', 0);
    }

    public function test_product_list_highlights_missing_partial_and_over_cost_allocations_and_filters_them(): void
    {
        $this->admin();
        $missing = Product::factory()->create(['title' => 'Missing Tape']);
        $complete = Product::factory()->create(['title' => 'Complete Tape', 'price' => 2.95]);
        $partial = Product::factory()->create(['title' => 'Partial Tape', 'price' => 2.95]);
        $expensive = Product::factory()->create(['title' => 'Over Cost Tape', 'price' => 2.95]);
        $inactive = ProductVariant::factory()->create(['product_id' => $complete->id, 'is_active' => false]);
        $editor = app(ProductAllocationEditor::class);
        $editor->save($complete, [], ['base' => $this->rules(50), 'variants' => []], auth()->id());
        $editor->save($partial, [], ['base' => ['fixed' => [2 => 50], 'percent' => []], 'variants' => []], auth()->id());
        $editor->save($expensive, [], ['base' => $this->rules(500), 'variants' => []], auth()->id());
        $response = $this->get(route('admin.shop.product.index', ['allocation_state' => 'needs_review']))->assertOk();
        $response->assertSeeText($missing->title)->assertSeeText($partial->title)->assertSeeText($expensive->title)->assertDontSeeText($complete->title)->assertSeeText('Allocation needs review');
        $this->get(route('admin.shop.product.index', ['allocation_state' => 'allocated']))->assertOk()->assertSeeText($complete->title)->assertDontSeeText($missing->title);
        DB::table('finance_product_allocations')->where('product_id', $complete->id)->update(['rules' => json_encode(['fixed' => [], 'percent' => [2 => 10000]])]);
        DB::table('finance_categories')->where('id', 2)->update(['active' => false]);
        $this->assertContains($complete->id, $editor->attentionIds());
    }
}
