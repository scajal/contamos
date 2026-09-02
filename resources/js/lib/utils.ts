import type { ClassValue } from "clsx";
import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";

import type { Currency } from "@/types/app/modules/core/catalog/models/currency";

type AmountCurrency = Pick<Currency, "decimal_places" | "symbol">;

/**
 * Merge class names into a single string.
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Format a major-unit amount for display (grouping + currency decimals + catalog symbol).
 */
export function formatAmount(amount: string, locale: string, currency?: AmountCurrency): string {
    // Get the decimal places of the currency.
    const decimalPlaces = currency?.decimal_places ?? 0;

    // Get the unsigned amount.
    const unsigned = amount.startsWith("-") ? amount.slice(1) : amount;

    // Get the integer and fraction parts of the amount.
    const [integerPart = "0", fractionPart = ""] = unsigned.split(".");

    // Format the integer part of the amount.
    const formattedInteger = new Intl.NumberFormat(locale, {
        maximumFractionDigits: 0,
        useGrouping: true,
    }).format(BigInt(integerPart.replace(/^0+(?=\d)/, "") || "0"));

    // Format the fraction part of the amount.
    const formatted =
        decimalPlaces === 0
            ? formattedInteger
            : `${formattedInteger}${new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === "decimal")?.value ?? "."}${fractionPart.slice(0, decimalPlaces).padEnd(decimalPlaces, "0")}`;

    return `${currency?.symbol} ${formatted}`;
}
