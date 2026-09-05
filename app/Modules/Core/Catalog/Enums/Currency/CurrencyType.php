<?php

declare(strict_types=1);

namespace App\Modules\Core\Catalog\Enums\Currency;

use Filament\Support\Contracts\HasLabel;

enum CurrencyType: string implements HasLabel
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

    /**
     * Get the label of the currency type.
     */
    public function getLabel(): string
    {
        return trans("enums.{$this->value}");
    }
}
