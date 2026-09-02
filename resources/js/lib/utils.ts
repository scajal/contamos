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
 * Convert a minor-unit integer to a major-unit number using the currency scale.
 */
export function toMajorAmount(amount: number, decimalPlaces: number): number {
    return amount / 10 ** decimalPlaces;
}

/**
 * Format a minor-unit amount for display (grouping + currency decimals + catalog symbol).
 */
export function formatAmount(amount: number, locale: string, currency?: AmountCurrency): string {
    const formatted = new Intl.NumberFormat(locale, {
        minimumFractionDigits: currency?.decimal_places ?? 0,
        maximumFractionDigits: currency?.decimal_places ?? 0,
        useGrouping: true,
    }).format(toMajorAmount(Math.abs(amount), currency?.decimal_places ?? 0));

    return `${currency?.symbol} ${formatted}`;
}
