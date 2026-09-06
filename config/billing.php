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
        /*
        |--------------------------------------------------------------------------
        | Free plan
        |--------------------------------------------------------------------------
        |
        | The free plan has a limited number of accounts, members, and transactions.
        |
        */

        'free' => [
            'accounts' => 0,
            'members' => 2,
            'teams' => 1,
            'transactions' => 100, // Per month.
        ],

        /*
        |--------------------------------------------------------------------------
        | Pro plan
        |--------------------------------------------------------------------------
        |
        | The pro plan has no limits.
        |
        */

        'pro' => [
            'accounts' => null,
            'members' => null,
            'teams' => null,
            'transactions' => null, // Per month.
        ],
    ],

];
