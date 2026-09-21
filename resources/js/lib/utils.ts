import type { ClassValue } from "clsx";
import { clsx } from "clsx";
import { formatDistanceToNow, isToday, isYesterday } from "date-fns";
import { enUS, es } from "date-fns/locale";
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
 * How long ago the given instant was, in words, in the current
 * locale. Timelines show this and keep the exact moment in the
 * title attribute.
 */
export function formatRelativeTime(value: string, locale: string): string {
    return formatDistanceToNow(new Date(value), {
        addSuffix: true,
        locale: locale.startsWith("es") ? es : enUS,
    });
}

/**
 * Format an instant as a date in the current locale. date-fns patterns such as
 * "MMMM d, yyyy" are English word order, so dates go through Intl instead and
 * read "19 de septiembre de 2026" in Spanish.
 *
 * Pass a Date for yyyy-MM-dd grouping keys, which are UTC midnight and shift
 * the calendar day west of UTC when read with new Date(): parse them first.
 */
export function formatDate(
    value: Date | string,
    locale: string,
    options: Intl.DateTimeFormatOptions = { dateStyle: "long" },
): string {
    return new Intl.DateTimeFormat(locale, options).format(value instanceof Date ? value : new Date(value));
}

/**
 * Format a month as its name and year in the current locale,
 * as the monthly page and its charts label periods.
 */
export function formatMonth(value: Date, locale: string, options: Intl.DateTimeFormatOptions = {}): string {
    return formatDate(value, locale, { month: "long", year: "numeric", ...options });
}

/**
 * Name the day a group of movements belongs to: today and yesterday read as
 * words, and every older day as its full date. The labels are passed in
 * already translated so this stays free of the i18n hook.
 */
export function formatDayHeading(value: Date, locale: string, labels: { today: string; yesterday: string }): string {
    if (isToday(value)) {
        return labels.today;
    }

    if (isYesterday(value)) {
        return labels.yesterday;
    }

    // dateStyle cannot be combined with weekday, so the parts are named.
    return formatDate(value, locale, {
        day: "numeric",
        month: "long",
        weekday: "long",
        year: "numeric",
    });
}

/**
 * Format a ratio as a percentage in the current locale, so it carries the
 * same decimal separator as the amounts it sits next to.
 */
export function formatPercentage(value: number, locale: string, maximumFractionDigits = 1): string {
    return new Intl.NumberFormat(locale, {
        maximumFractionDigits,
        style: "percent",
    }).format(value / 100);
}

/**
 * Notify an Inertia <Form> that values changed. Custom controls like Combobox
 * and ToggleGroup update hidden inputs in React without firing native events.
 */
export function markFormDirty(from?: Element | null): void {
    requestAnimationFrame(() => {
        const form =
            (from instanceof Element ? from.closest("form") : null) ??
            (document.activeElement instanceof Element ? document.activeElement.closest("form") : null);

        form?.dispatchEvent(new Event("input", { bubbles: true }));
    });
}

/**
 * The character the locale writes decimals with.
 */
function decimalSeparator(locale: string): string {
    return new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === "decimal")?.value ?? ".";
}

/**
 * Drop the zeros the currency's scale padded on, keeping the two decimals
 * money is normally written with. A currency with fewer than two decimals
 * keeps all of them, and a digit that is not a zero is never removed.
 */
function trimPaddedDecimals(formatted: string, locale: string, decimalPlaces: number): string {
    const separator = decimalSeparator(locale);
    const [integer, fraction] = formatted.split(separator);

    if (fraction === undefined || decimalPlaces <= 2) {
        return formatted;
    }

    const kept = fraction.replace(/0+$/, "").padEnd(2, "0");

    return `${integer}${separator}${kept}`;
}

/**
 * Format a major-unit amount for display (grouping + currency decimals + catalog symbol).
 * Tables already grouped by currency pass `symbol: false` so the code is not
 * repeated in every cell.
 */
export function formatAmount(
    amount: string,
    locale: string,
    currency?: AmountCurrency,
    options: { symbol?: boolean; trimZeros?: boolean } = {},
): string {
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
            : `${formattedInteger}${decimalSeparator(locale)}${fractionPart.slice(0, decimalPlaces).padEnd(decimalPlaces, "0")}`;

    // A total pads to the currency's scale, which turns a six-decimal crypto
    // balance into a figure too wide to read. Summaries drop the padding
    // beyond the two decimals money is normally written with, and never a
    // digit the amount actually carries.
    const trimmed = options.trimZeros ? trimPaddedDecimals(formatted, locale, decimalPlaces) : formatted;

    return options.symbol === false ? trimmed : `${currency?.symbol} ${trimmed}`;
}

/**
 * The status of a team invitation, derived from its
 * accepted and expiration dates.
 */
export type InvitationStatus = "accepted" | "expired" | "pending";

/**
 * Derive the status of a team invitation from its
 * accepted and expiration dates.
 */
export function invitationStatus(invitation: {
    accepted_at: string | null;
    expires_at: string | null;
}): InvitationStatus {
    if (invitation.accepted_at) {
        return "accepted";
    }

    if (invitation.expires_at && new Date(invitation.expires_at) <= new Date()) {
        return "expired";
    }

    return "pending";
}

/**
 * The initials of the given name, used as the
 * avatar fallback.
 */
export function initials(name: string): string {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? "")
        .join("");
}
