<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Paddle price ids for each paid plan and billing interval.
    |
    */

    'plans' => [
        'pro' => [
            'prices' => [
                'month' => env('PADDLE_PRICE_PRO_MONTHLY'),
                'year' => env('PADDLE_PRICE_PRO_YEARLY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Per-plan feature limits. A null value means the feature is unlimited
    | on that plan.
    |
    */

    'limits' => [
        'free' => [
            'accounts' => 3,
            'members' => 2,
            'transactions' => 100,
            'teams' => 1,
        ],
        'pro' => [
            'accounts' => null,
            'members' => null,
            'transactions' => null,
            'teams' => null,
        ],
    ],

];
