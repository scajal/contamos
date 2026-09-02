/**
 * JSON shape of App\Modules\Core\Catalog\Models\Currency when serialized for Inertia.
 */
import type { ExchangeRate } from "./currency/exchange-rate";

export interface Currency {
    id: string;
    code: string;
    decimal_places: number;
    name: string;
    symbol: string;
    created_at: string | null;
    updated_at: string | null;
    exchangeRates?: ExchangeRate[];
}
