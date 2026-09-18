<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Objects;

/**
 * The amount, resolved quote, and instant for one currency's
 * opening balance, resolved by the account Store/Update request
 * and consumed by the Create/Update account actions.
 */
final readonly class OpeningBalance
{
    public function __construct(
        public string $amount,
        public string $quote,
        public string $occurredAt,
    ) {}
}
