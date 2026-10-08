<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property-read float|null $reservation_shortage */
class StockItem extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STOCK_ISSUE_INCREMENT = 1.0;

    public const SHARED_WORKSHOP_RESERVATION_BUFFER_PERCENT = 0.10;

    protected $fillable = [
        'name', 'sku', 'image_media_name', 'unit', 'status', 'is_kit', 'on_hand_quantity',
        'stock_item_group_id', 'variant_name', 'reorder_point', 'shared_workshop_supply', 'replacement_unit_cost_ex_tax',
        'replacement_cost_currency', 'replacement_cost_source', 'replacement_cost_updated_at',
        'notes',
    ];

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_name', 'name');
    }

    /** @return BelongsTo<StockItemGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(StockItemGroup::class, 'stock_item_group_id');
    }

    public function linkLabel(): string
    {
        if ($this->is_kit) {
            return (string) $this->name;
        }

        $groupName = trim((string) $this->group?->name);
        $variantName = trim((string) $this->variant_name);

        return $groupName !== '' && $variantName !== ''
            ? $groupName.' — '.$variantName
            : (string) $this->name;
    }

    public function stockIssueIncrement(): float
    {
        return self::STOCK_ISSUE_INCREMENT;
    }

    public function roundStockIssueQuantity(float $quantity): float
    {
        $quantity = max(0, $quantity);

        return round(ceil(($quantity - 0.0000001) / self::STOCK_ISSUE_INCREMENT) * self::STOCK_ISSUE_INCREMENT, 3);
    }

    public static function estimateMaterialUse(float $units, float $quantityPerUnit, float $increment): float
    {
        if ($units <= 0 || $quantityPerUnit <= 0 || $increment <= 0) {
            return 0;
        }

        $capacityPerIncrement = (int) floor(($increment + 0.000001) / $quantityPerUnit);
        if ($capacityPerIncrement < 1) {
            return round($units * $quantityPerUnit, 3);
        }

        $fullIncrements = (int) floor(($units + 0.000001) / $capacityPerIncrement);
        $remainingUnits = max(0, $units - ($fullIncrements * $capacityPerIncrement));

        return round(($fullIncrements * $increment) + ($remainingUnits * $quantityPerUnit), 3);
    }

    protected $casts = [
        'on_hand_quantity' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'shared_workshop_supply' => 'boolean',
        'stock_item_group_id' => 'integer',
        'replacement_unit_cost_ex_tax' => 'decimal:4',
        'replacement_cost_updated_at' => 'datetime',
        'is_kit' => 'boolean',
    ];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<StockItemComponent, $this> */
    public function kitComponents(): HasMany
    {
        return $this->hasMany(StockItemComponent::class, 'kit_stock_item_id')->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<StockItemComponent, $this> */
    public function usedInKits(): HasMany
    {
        return $this->hasMany(StockItemComponent::class, 'component_stock_item_id');
    }

    /** @return HasMany<PickListTemplateItem, $this> */
    public function pickListItems(): HasMany
    {
        return $this->hasMany(PickListTemplateItem::class);
    }

    /** @return HasMany<StockReceiptLine, $this> */
    public function receiptLines(): HasMany
    {
        return $this->hasMany(StockReceiptLine::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<StockReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /** @return HasMany<StockReservation, $this> */
    public function activeReservations(): HasMany
    {
        return $this->reservations()->whereIn('status', StockReservation::ACTIVE_STATUSES);
    }

    public function activeReservedQuantity(): float
    {
        return app(\App\Services\StockInventoryService::class)->reservedQuantityForStockItem($this);
    }

    public function tracksInventory(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function availableQuantity(): ?float
    {
        if (! $this->tracksInventory()) {
            return null;
        }

        if ($this->is_kit) {
            return app(\App\Services\StockInventoryService::class)->availableStockItemQuantity($this);
        }

        return max(0, (float) $this->on_hand_quantity - $this->activeReservedQuantity());
    }

    public function replacementCost(): ?float
    {
        if ($this->is_kit) {
            return app(\App\Services\StockInventoryService::class)->replacementCostForStockItem($this);
        }

        return $this->replacement_unit_cost_ex_tax === null
            ? null
            : max(0, (float) $this->replacement_unit_cost_ex_tax);
    }

    public function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ','), '0'), '.');
    }

    public function formatUnitCost(float $cost): string
    {
        $formatted = number_format($cost, 4, '.', ',');
        [$whole, $fraction] = explode('.', $formatted, 2);
        $fraction = rtrim($fraction, '0');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    public function needsReorder(): bool
    {
        $available = $this->availableQuantity();

        return $available !== null
            && (float) $this->reorder_point > 0
            && $available < (float) $this->reorder_point;
    }
}
