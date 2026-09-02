/**
 * JSON shape of App\Modules\Core\Catalog\Models\Currency when serialized for Inertia.
 */
import { CurrencyType } from "../enums/currency/currency-type";
import type { ExchangeRate } from "./currency/exchange-rate";

export interface Currency {
    id: string;
    code: string;
    decimal_places: number;
    is_active: boolean;
    name: string;
    symbol: string;
    type: CurrencyType;
    created_at: string | null;
    updated_at: string | null;
    exchangeRates?: ExchangeRate[];
}
