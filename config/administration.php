<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Operators
    |--------------------------------------------------------------------------
    |
    | The email addresses allowed into the Filament administration and team
    | panels, as a comma separated list. Operators can support every team
    | without being a member of it, so keep this list short.
    |
    */

    'operators' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('ADMINISTRATION_OPERATORS', '')),
    ))),
];
