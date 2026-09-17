<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Enums\Account;

use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasLabel
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

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => trans('Bank'),
            self::Cash => trans('Cash'),
            self::Crypto => trans('Crypto'),
        };
    }
}
