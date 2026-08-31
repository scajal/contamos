<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Account;

enum AccountType: string
{
    /**
     * Represents bank accounts.
     */
    case Bank = 'bank';

    /**
     * Represents cash accounts.
     */
    case Cash = 'cash';

    /**
     * Represents crypto accounts or wallets.
     */
    case Crypto = 'crypto';
}
