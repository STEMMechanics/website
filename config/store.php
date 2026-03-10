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
        'boxed_shipping' => [
            'label' => 'Boxed shipping required',
            'message' => 'This order cannot be packed into satchels and needs boxed shipping.',
            'amount' => null,
        ],
    ],
];
