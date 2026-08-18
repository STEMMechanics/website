<?php

return [
    'shipping' => [
        'max_satchel_weight_grams' => 5000,
        'satchels' => [
            [
                'code' => 'small',
                'label' => 'Small',
                'rank' => 1,
                'capacity' => 1.0,
                'price' => 9.95,
                'active' => true,
            ],
            [
                'code' => 'medium',
                'label' => 'Medium',
                'rank' => 2,
                'capacity' => 2.0,
                'price' => 12.95,
                'active' => true,
            ],
            [
                'code' => 'large',
                'label' => 'Large',
                'rank' => 3,
                'capacity' => 3.0,
                'price' => 15.95,
                'active' => true,
            ],
            [
                'code' => 'extra_large',
                'label' => 'Extra Large',
                'rank' => 4,
                'capacity' => 4.0,
                'price' => 18.95,
                'active' => true,
            ],
        ],
        'boxes' => [
            ['code' => 'box_220_160_70', 'label' => '220 × 160 × 70 mm Box', 'rank' => 1, 'length_mm' => 220, 'width_mm' => 160, 'height_mm' => 70, 'max_weight_grams' => 5000, 'price' => 12.38, 'active' => true],
            ['code' => 'box_240_190_120', 'label' => '240 × 190 × 120 mm Box', 'rank' => 2, 'length_mm' => 240, 'width_mm' => 190, 'height_mm' => 120, 'max_weight_grams' => 5000, 'price' => 16.56, 'active' => true],
            ['code' => 'box_390_280_140', 'label' => '390 × 280 × 140 mm Box', 'rank' => 3, 'length_mm' => 390, 'width_mm' => 280, 'height_mm' => 140, 'max_weight_grams' => 5000, 'price' => 20.93, 'active' => true],
            ['code' => 'box_440_277_168', 'label' => '440 × 277 × 168 mm Box', 'rank' => 4, 'length_mm' => 440, 'width_mm' => 277, 'height_mm' => 168, 'max_weight_grams' => 5000, 'price' => 25.09, 'active' => true],
        ],
        'boxed_shipping' => [
            'label' => 'Boxed shipping required',
            'message' => 'This order cannot be packed into satchels and needs boxed shipping.',
            'amount' => null,
        ],
    ],
];
