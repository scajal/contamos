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
