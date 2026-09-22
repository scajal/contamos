import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";
import { Frequency } from "@/types/app/modules/core/finance/enums/recurring-rule/frequency";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { RecurringRule } from "@/types/app/modules/core/finance/models/team/recurring-rule";

/**
 * The value the type filter uses to match both legs of a transfer.
 */
export const TRANSFER_TYPE = "transfer";

/**
 * The values the transactions index type filter accepts.
 */
export type TransactionTypeFilter = TransactionType | typeof TRANSFER_TYPE;

/**
 * The sign shown before a transaction's amount, by type.
 */
export const TRANSACTION_AMOUNT_SIGN: Record<TransactionType, string> = {
    expense: "-",
    income: "+",
    opening_balance: "+",
    transfer_in: "+",
    transfer_out: "-",
};

/**
 * The color of a transaction's amount, by type.
 */
export const TRANSACTION_AMOUNT_COLOR: Record<TransactionType, string> = {
    expense: "text-destructive",
    income: "text-success",
    opening_balance: "text-muted-foreground",
    transfer_in: "text-muted-foreground",
    transfer_out: "text-muted-foreground",
};

/**
 * Shared column widths so day-grouped tables stay aligned.
 * Fixed layout + identical widths keep columns consistent across separate tables.
 */
export const TRANSACTION_TABLE_CLASS = "table-fixed min-w-[44rem]";
export const TRANSACTION_COLUMNS = {
    time: "w-16",
    account: "w-40",
    description: "w-48",
    tags: "w-36",
    author: "w-10",
    amount: "w-36",
    actions: "w-12",
} as const;

/**
 * Format a Date as a datetime-local input value (YYYY-MM-DDTHH:mm).
 */
export function toDateTimeLocalValue(value?: string): string {
    const date = value === undefined ? new Date() : parseDateTimeLocal(value);
    const resolved = Number.isNaN(date.getTime()) ? new Date() : date;
    const pad = (part: number) => String(part).padStart(2, "0");

    return `${resolved.getFullYear()}-${pad(resolved.getMonth() + 1)}-${pad(resolved.getDate())}T${pad(resolved.getHours())}:${pad(resolved.getMinutes())}`;
}

/**
 * Parse a datetime-local string as wall-clock time in the browser timezone.
 */
export function parseDateTimeLocal(value: string): Date {
    const [datePart, timePart = "00:00"] = value.split("T");
    const [year, month, day] = datePart.split("-").map(Number);
    const [hour, minute] = timePart.split(":").map(Number);

    return new Date(year, (month ?? 1) - 1, day ?? 1, hour ?? 0, minute ?? 0);
}

/**
 * Whether the two dates are the same day in the local timezone.
 */
export function isSameLocalDay(left: Date, right: Date): boolean {
    return (
        left.getFullYear() === right.getFullYear() &&
        left.getMonth() === right.getMonth() &&
        left.getDate() === right.getDate()
    );
}

/**
 * Set the date of the occurred at value to the provided date.
 */
export function withLocalDate(occurredAt: string, date: Date): string {
    const current = parseDateTimeLocal(occurredAt);
    const pad = (part: number) => String(part).padStart(2, "0");

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(current.getHours())}:${pad(current.getMinutes())}`;
}

/**
 * Set the time of the occurred at value to the provided time.
 */
export function withLocalTime(occurredAt: string, time: string): string {
    const [datePart] = occurredAt.split("T");

    return `${datePart}T${time || "00:00"}`;
}

/**
 * Get the quote for the currency at the provided date.
 */
export function quoteAsOf(currency: Currency | null, at: Date): Quote | null {
    if (!currency) {
        return null;
    }

    const atMs = at.getTime();
    let best: Quote | null = null;
    let bestMs = Number.NEGATIVE_INFINITY;

    for (const quote of currency.quotes ?? []) {
        const observedMs = new Date(quote.observed_at).getTime();

        if (Number.isNaN(observedMs) || observedMs > atMs) {
            continue;
        }

        if (observedMs >= bestMs) {
            best = quote;
            bestMs = observedMs;
        }
    }

    return best;
}

/**
 * The singular and plural unit noun keys for each recurring frequency,
 * e.g. "day"/"days" for daily, used beside an interval such as "1 month"
 * or "2 months" rather than the frequency's own adjective ("Monthly").
 */
export const FREQUENCY_UNITS: Record<Frequency, [string, string]> = {
    daily: ["day", "days"],
    weekly: ["week", "weeks"],
    monthly: ["month", "months"],
    yearly: ["year", "years"],
};

/**
 * Get the singular or plural unit noun key for the frequency, based on
 * the interval.
 */
export function frequencyUnitKey(frequency: Frequency, interval: number): string {
    const [singular, plural] = FREQUENCY_UNITS[frequency];

    return interval === 1 ? singular : plural;
}

/**
 * Describe a rule's schedule in words, such as "Every month on the 3rd"
 * or "Every 2 weeks on Monday", so a member reads the cadence rather
 * than decoding a frequency and an interval.
 */
export function scheduleLabel(
    rule: RecurringRule,
    locale: string,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): string {
    const every =
        rule.interval === 1
            ? t("Every :unit", { unit: t(frequencyUnitKey(rule.frequency, 1)) })
            : t("Every :count :unit", {
                  count: rule.interval,
                  unit: t(frequencyUnitKey(rule.frequency, rule.interval)),
              });

    const anchor = new Date(`${rule.starts_on.slice(0, 10)}T00:00:00`);

    switch (rule.frequency) {
        case "weekly":
            return `${every} · ${new Intl.DateTimeFormat(locale, { weekday: "long" }).format(anchor)}`;
        case "monthly":
            return `${every} · ${new Intl.DateTimeFormat(locale, { day: "numeric" }).format(anchor)}`;
        case "yearly":
            return `${every} · ${new Intl.DateTimeFormat(locale, { day: "numeric", month: "long" }).format(anchor)}`;
        default:
            return every;
    }
}

/**
 * Strip padded decimal:18 zeros so a catalog rate can sit in an input.
 */
export function trimTrailingZeros(value: string): string {
    if (!value.includes(".")) {
        return value;
    }

    return value.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}
