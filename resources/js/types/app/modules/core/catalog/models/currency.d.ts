/**
 * JSON shape of App\Modules\Core\Catalog\Models\Currency when serialized for Inertia.
 */
import { CurrencyType } from "../enums/currency/currency-type";
import { QuoteSource } from "../enums/currency/quote/quote-source";
import { Quote } from "./currency/quote";

export interface Currency {
    id: string;
    code: string;
    decimal_places: number;
    is_active: boolean;
    name: string;
    quote_source: QuoteSource;
    symbol: string;
    type: CurrencyType;
    created_at: string | null;
    updated_at: string | null;
    currentQuote?: Quote | null;
    quotes?: Array<Quote>;
}
