/**
 * JSON shape of App\Modules\Core\Catalog\Models\Currency\Quote when serialized for Inertia.
 */
import type { User } from "../../../identity/models/user";
import type { QuoteSource } from "../../enums/currency/quote/quote-source";
import type { Currency } from "../currency";

export interface Quote {
    id: string;
    currency_id: string;
    observed_at: string;
    rate: string;
    recorded_by: string | null;
    source: QuoteSource;
    created_at: string | null;
    updated_at: string | null;
    currency?: Currency;
    recorder?: User | null;
}
