<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Here you may define the plans that are available in your application.
    |
    */

    'plans' => [
        /*
        |--------------------------------------------------------------------------
        | Free Plan
        |--------------------------------------------------------------------------
        |
        | Here you may define the free plan that is available in your application.
        |
        */

        'free' => [
            /**
             * The limits for the free plan.
             */
            'limits' => [
                'accounts' => null,
                'members' => 1,
                'transactionsPerMonth' => 100,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Pro Plan
        |--------------------------------------------------------------------------
        |
        | Here you may define the pro plan that is available in your application.
        |
        */

        'pro' => [
            /**
             * The product ID for the pro plan.
             */
            'productId' => env('PRODUCT_PRO_ID'),

            /**
             * The limits for the pro plan.
             */
            'limits' => [
                'accounts' => null,
                'members' => null,
                'transactionsPerMonth' => null,
            ],

            /**
             * The price IDs for the pro plan.
             */
            'prices' => [
                'monthly' => env('PRODUCT_PRO_MONTHLY_PRICE'),
                'yearly' => env('PRODUCT_PRO_YEARLY_PRICE'),
            ],
        ],
    ],
];
