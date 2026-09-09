<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Workshop;
use App\Support\ShopAvailability;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WorkshopEquipmentService
{
    public function products(Workshop $workshop): Collection
    {
        return Product::query()->active()->with(['variants', 'hero'])->whereIn('id', $workshop->optional_product_ids ?? [])->orderBy('title')->get();
    }

    public function cart(Workshop $workshop): StoreCartService
    {
        return new StoreCartService(app(StoreShippingService::class), app(StoreCouponService::class), 'workshop_equipment.'.hash('sha256', $workshop->id));
    }

    public function select(Workshop $workshop, array $quantities, array $variants): void
    {
        $cart = $this->cart($workshop);
        $products = $this->products($workshop)->keyBy('id');
        $selected = [];
        foreach ($quantities as $id => $quantity) {
            if ((int) $quantity === 0) {
                continue;
            }
            $product = $products->get($id);
            if (! $product) {
                throw ValidationException::withMessages(['equipment' => 'This product is no longer available with this workshop.']);
            }
            $variantId = $variants[$id] ?? null;
            $variant = $variantId ? $product->variants->firstWhere('id', (int) $variantId) : null;
            if ($variantId && ! $variant) {
                throw ValidationException::withMessages(['equipment' => 'Choose a valid product option.']);
            }
            $selected[] = [$product, $variant, (int) $quantity];
        }
        $cart->clear();
        foreach ($selected as [$product, $variant, $quantity]) {
            $cart->add($product, $variant, $quantity);
        }
    }

    public function summary(Workshop $workshop, array $session): array
    {
        $cart = $this->cart($workshop);
        if (empty($cart->contents()['lines'])) {
            return ['cart' => $cart, 'lines' => collect(), 'summary' => ['total' => 0.0, 'shipping' => 0.0, 'can_checkout' => true, 'shipping_methods' => []]];
        }
        $allowedIds = $this->products($workshop)->pluck('id')->all();
        foreach ($cart->contents()['lines'] ?? [] as $line) {
            if (! in_array((int) $line['product_id'], $allowedIds, true)) {
                throw ValidationException::withMessages(['equipment' => 'An equipment item is no longer offered. Please review your equipment selection.']);
            }
        }
        $summary = $cart->summary($session['equipment_customer'] ?? []);
        if ($summary['shipping_quote']['requires_manual_quote'] ?? false) {
            $summary['total'] = 0.0;
        }

        return ['cart' => $cart, 'lines' => $cart->lines(), 'summary' => $summary];
    }

    public function assertReady(array $equipment, ?float $confirmedTotal, ?array $confirmedLines = null): void
    {
        $summary = $equipment['summary'];
        if ($confirmedLines !== null && $confirmedLines !== ($equipment['cart']->contents()['lines'] ?? [])) {
            throw ValidationException::withMessages(['equipment' => 'Equipment availability changed. Please review the selected items before paying.']);
        }
        if ($equipment['lines']->isNotEmpty() && ! app(ShopAvailability::class)->isPublicEnabled()) {
            throw ValidationException::withMessages(['equipment' => 'The store is currently unavailable. Continue without equipment or try again later.']);
        }
        if ($equipment['cart']->inventoryChangeNotices() !== [] || (! ($summary['can_checkout'] ?? true) && ! ($summary['shipping_quote']['requires_manual_quote'] ?? false))) {
            throw ValidationException::withMessages(['equipment' => 'Equipment availability or delivery needs review. Please return to equipment and choose an available delivery option.']);
        }
        if ($confirmedTotal === null || abs((float) $summary['total'] - $confirmedTotal) > 0.001) {
            throw ValidationException::withMessages(['equipment' => 'Equipment prices or delivery have changed. Please review the total before paying.']);
        }
    }
}
