<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Account;

enum AccountType: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Crypto = 'crypto';
}
