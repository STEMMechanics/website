<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'product_id',
        'product_variant_id',
        'invoice_line_id',
        'product_title',
        'product_slug',
        'variant_name',
        'product_sku',
        'variant_sku',
        'product_type',
        'box_only',
        'quantity',
        'inventory_reserved_quantity',
        'unit_shipping_units',
        'unit_min_satchel_rank',
        'unit_price',
        'unit_shipping_rate',
        'tax_rate',
        'unit_weight_grams',
        'unit_length_cm',
        'unit_width_cm',
        'unit_height_cm',
        'line_price_amount',
        'line_shipping_amount',
        'line_gst_amount',
        'line_total_amount',
    ];

    protected $casts = [
        'box_only' => 'boolean',
        'quantity' => 'integer',
        'inventory_reserved_quantity' => 'integer',
        'unit_shipping_units' => 'decimal:2',
        'unit_min_satchel_rank' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_shipping_rate' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'unit_weight_grams' => 'integer',
        'unit_length_cm' => 'decimal:2',
        'unit_width_cm' => 'decimal:2',
        'unit_height_cm' => 'decimal:2',
        'line_price_amount' => 'decimal:2',
        'line_shipping_amount' => 'decimal:2',
        'line_gst_amount' => 'decimal:2',
        'line_total_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<StoreOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /**
     * @return HasMany<StoreOrderItemDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(StoreOrderItemDownload::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isDigital(): bool
    {
        return (string) $this->product_type === Product::PRODUCT_TYPE_DIGITAL;
    }

    public function displayTitle(): string
    {
        $variantName = trim((string) $this->variant_name);
        if ($variantName === '') {
            return (string) $this->product_title;
        }

        return (string) $this->product_title.' - '.$variantName;
    }

    public function reservedInventory(): int
    {
        return max(0, (int) $this->inventory_reserved_quantity);
    }
}
