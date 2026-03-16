<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_shipping_method_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_shipping_method_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('label', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('capacity', 8, 2);
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_shipping_method_id', 'code'], 'ship_method_pkg_code_unique');
        });

        $defaultPackages = [
            [
                'code' => 'small',
                'label' => 'Small',
                'sort_order' => 1,
                'capacity' => 1.00,
                'price' => 9.95,
                'is_active' => true,
            ],
            [
                'code' => 'medium',
                'label' => 'Medium',
                'sort_order' => 2,
                'capacity' => 2.00,
                'price' => 12.95,
                'is_active' => true,
            ],
            [
                'code' => 'large',
                'label' => 'Large',
                'sort_order' => 3,
                'capacity' => 3.00,
                'price' => 15.95,
                'is_active' => true,
            ],
            [
                'code' => 'extra_large',
                'label' => 'Extra Large',
                'sort_order' => 4,
                'capacity' => 4.00,
                'price' => 18.95,
                'is_active' => true,
            ],
        ];

        $configuredPackages = $defaultPackages;
        if (Schema::hasTable('site_options')) {
            $packagesJson = DB::table('site_options')
                ->whereIn('name', ['store.shipping.satchels', 'shop.shipping.satchels'])
                ->orderByRaw("case when name = 'store.shipping.satchels' then 0 else 1 end")
                ->value('value');

            $decoded = json_decode((string) $packagesJson, true);
            if (is_array($decoded) && $decoded !== []) {
                $configuredPackages = collect($decoded)
                    ->map(function ($package): array {
                        $package = is_array($package) ? $package : [];

                        return [
                            'code' => trim((string) ($package['code'] ?? '')),
                            'label' => trim((string) ($package['label'] ?? 'Package')) ?: 'Package',
                            'sort_order' => max(1, (int) ($package['rank'] ?? $package['sort_order'] ?? 1)),
                            'capacity' => round(max(0.01, (float) ($package['capacity'] ?? 0.01)), 2),
                            'price' => round(max(0, (float) ($package['price'] ?? 0)), 2),
                            'is_active' => filter_var($package['active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                        ];
                    })
                    ->filter(fn (array $package): bool => $package['code'] !== '')
                    ->sortBy('sort_order')
                    ->values()
                    ->all();
            }
        }

        $timestamp = now();
        $methods = DB::table('store_shipping_methods')->get();

        foreach ($methods as $method) {
            if ((bool) ($method->is_pickup ?? false)) {
                DB::table('store_shipping_methods')
                    ->where('id', $method->id)
                    ->update([
                        'calculator' => 'pickup',
                        'updated_at' => $timestamp,
                    ]);

                continue;
            }

            $multiplier = max(0, (float) ($method->rate_multiplier ?? 1));
            $adjustment = (float) ($method->rate_adjustment_amount ?? 0);

            foreach ($configuredPackages as $package) {
                DB::table('store_shipping_method_packages')->insert([
                    'store_shipping_method_id' => $method->id,
                    'code' => (string) $package['code'],
                    'label' => (string) $package['label'],
                    'sort_order' => (int) $package['sort_order'],
                    'capacity' => round((float) $package['capacity'], 2),
                    'price' => round(max(0, ((float) $package['price'] * $multiplier) + $adjustment), 2),
                    'is_active' => (bool) $package['is_active'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            DB::table('store_shipping_methods')
                ->where('id', $method->id)
                ->update([
                    'calculator' => 'packages',
                    'flat_rate_amount' => null,
                    'rate_multiplier' => 1,
                    'rate_adjustment_amount' => 0,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_shipping_method_packages');
    }
};
