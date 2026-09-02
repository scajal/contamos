<?php

namespace App\Modules\Core\Catalog\Enums\Currency;

enum CurrencyType: string
{
    /**
     * Cryptocurrencies are digital currencies that are not issued by a central bank.
     */
    case Cryptocurrency = 'cryptocurrency';

    /**
     * Fiat currencies are physical currencies that are issued by central banks.
     */
    case Fiat = 'fiat';

    /**
     * Virtual currencies are currencies created by a company
     * or organization (like loyalty points).
     */
    case Virtual = 'virtual';
}
