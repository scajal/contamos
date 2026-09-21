<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Legal Entity
    |--------------------------------------------------------------------------
    |
    | Who is behind Contamos, where it is established and how it can be
    | reached. These values are interpolated into the privacy policy, the
    | terms of service and the refund policy, so they are set once here
    | rather than written into the copy.
    |
    */

    'entity' => env('LEGAL_ENTITY', 'Contamos'),

    'jurisdiction' => env('LEGAL_JURISDICTION', 'Uruguay'),

    'address' => env('LEGAL_ADDRESS', ''),

    /*
    |--------------------------------------------------------------------------
    | Revision
    |--------------------------------------------------------------------------
    |
    | The date the legal documents were last changed, shown on each of
    | them, and whether the text has been reviewed by a lawyer. While the
    | review flag is false the pages carry a visible notice saying the
    | text is a placeholder, so unreviewed clauses can never pass for
    | the real thing.
    |
    */

    'updated_at' => env('LEGAL_UPDATED_AT', '2026-09-21'),

    'reviewed' => (bool) env('LEGAL_REVIEWED', false),
];
