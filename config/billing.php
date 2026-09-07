<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Here you may define the plans that are available for your application.
    |
    */

    'plans' => [
        /*
        |--------------------------------------------------------------------------
        | Pro Plan
        |--------------------------------------------------------------------------
        |
        | Here you may define the pro plan settings.
        |
        */

        'pro' => [
            'id' => env('PRODUCT_PRO_ID'),
            'monthly_price' => env('PRODUCT_PRO_MONTHLY_PRICE'),
            'yearly_price' => env('PRODUCT_PRO_YEARLY_PRICE'),
        ],
    ],
];
