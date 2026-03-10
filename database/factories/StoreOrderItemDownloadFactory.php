<?php

namespace Database\Factories;

use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreOrderItemDownloadFactory extends Factory
{
    protected $model = StoreOrderItemDownload::class;

    public function definition(): array
    {
        return [
            'store_order_item_id' => StoreOrderItem::factory(),
            'media_name' => fake()->lexify('download-????').'.pdf',
            'title' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
