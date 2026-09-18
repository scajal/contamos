import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, CopyIcon } from "lucide-react";
import { useState } from "react";

import { ConfirmAction } from "@/components/confirm-action";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn, formatAmount } from "@/lib/utils";
import transactions from "@/routes/finance/teams/transactions";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";

type PageProps = {
    transaction: Transaction;
};

const USD = { decimal_places: 2, symbol: "$" } as const;

/**
 * The sign shown before a transaction's amount, by type.
 */
const TRANSACTION_AMOUNT_SIGN: Record<TransactionType, string> = {
    expense: "-",
    income: "+",
    opening_balance: "+",
};

/**
 * The color of a transaction's amount, by type.
 */
const TRANSACTION_AMOUNT_COLOR: Record<TransactionType, string> = {
    expense: "text-destructive",
    income: "text-success",
    opening_balance: "text-muted-foreground",
};

export default function Show({ transaction }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * The currency of the movement, when the relation loaded.
     */
    const currency = transaction.currency;

    /**
     * Whether the movement is already in US dollars.
     */
    const isUsd = currency?.code === "USD";

    /**
     * Description, if the recorder wrote one.
     */
    const description = transaction.description?.trim() || null;

    /**
     * Tags attached to the movement.
     */
    const tags = transaction.tags ?? [];

    /**
     * Who recorded the movement, if known.
     */
    const recorder = transaction.user?.name?.trim() || null;

    /**
     * The quote as a rate against one US dollar.
     */
    const rate = isUsd ? null : formatRate(transaction.quote, currentLocale());

    /**
     * The movement expressed in US dollars, when a non-USD rate exists.
     */
    const usdAmount = isUsd ? null : divideDecimals(transaction.amount, transaction.quote, USD.decimal_places);

    /**
     * When the movement occurred, in the current locale.
     */
    const occurredAt = new Intl.DateTimeFormat(currentLocale(), {
        dateStyle: "long",
        timeStyle: "short",
    }).format(new Date(transaction.occurred_at));

    return (
        <Modal>
            {({ rootProps }) => (
                <Sheet {...rootProps}>
                    <SheetContent>
                        <SheetHeader>
                            <SheetTitle>{t("Transaction details")}</SheetTitle>
                            <SheetDescription suppressHydrationWarning>{occurredAt}</SheetDescription>
                        </SheetHeader>
                        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6">
                            <FieldGroup>
                                <div className="flex items-baseline justify-between gap-3">
                                    <p
                                        className={cn(
                                            "text-2xl font-semibold tabular-nums",
                                            TRANSACTION_AMOUNT_COLOR[transaction.type],
                                        )}
                                    >
                                        {`${TRANSACTION_AMOUNT_SIGN[transaction.type]} ${formatAmount(transaction.amount, currentLocale(), currency)}`}
                                        {currency?.code ? (
                                            <span className="text-muted-foreground ml-1.5 text-sm font-medium">
                                                {currency.code}
                                            </span>
                                        ) : null}
                                    </p>
                                    <span className="text-muted-foreground shrink-0 text-sm">
                                        {t(`enums.${transaction.type}`)}
                                    </span>
                                </div>

                                {transaction.account?.name ? (
                                    <Field>
                                        <FieldLabel>{t("Account")}</FieldLabel>
                                        <p className="text-sm">{transaction.account.name}</p>
                                    </Field>
                                ) : null}

                                {description ? (
                                    <Field>
                                        <FieldLabel>{t("Description")}</FieldLabel>
                                        <p className="text-sm text-pretty">{description}</p>
                                    </Field>
                                ) : null}

                                {tags.length > 0 ? (
                                    <Field>
                                        <FieldLabel>{t("Tags")}</FieldLabel>
                                        <div className="flex flex-wrap gap-1">
                                            {tags.map((tag) => (
                                                <Badge key={tag.id} variant="outline">
                                                    {tag.name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </Field>
                                ) : null}

                                {rate && currency ? (
                                    <Field>
                                        <FieldLabel>{t("Rate")}</FieldLabel>
                                        <div className="flex items-baseline justify-between gap-3">
                                            <p className="min-w-0 text-sm tabular-nums">
                                                {t("1 US dollar = :code", { code: `${rate} ${currency.code}` })}
                                            </p>
                                            {usdAmount ? (
                                                <p className="text-muted-foreground shrink-0 text-sm tabular-nums">
                                                    {`≈ ${formatAmount(usdAmount, currentLocale(), USD)}`}
                                                </p>
                                            ) : null}
                                        </div>
                                    </Field>
                                ) : null}

                                {recorder ? (
                                    <Field>
                                        <FieldLabel>{t("Recorded by")}</FieldLabel>
                                        <p className="text-sm">{recorder}</p>
                                    </Field>
                                ) : null}

                                <Field>
                                    <FieldLabel>{t("Transaction ID")}</FieldLabel>
                                    <div className="flex min-w-0 items-center gap-1">
                                        <p className="text-muted-foreground min-w-0 truncate font-mono text-xs">
                                            {transaction.id}
                                        </p>
                                        <CopyIdButton value={transaction.id} />
                                    </div>
                                </Field>
                            </FieldGroup>
                        </div>
                        <SheetFooter>
                            <div className="self-start">
                                <ConfirmAction
                                    title={t("Are you sure you want to delete the transaction?")}
                                    description={t("This action cannot be undone.")}
                                    confirmLabel={t("Delete")}
                                    action={
                                        <Link
                                            href={transactions.destroy.url({
                                                team: user?.current_team?.slug ?? "",
                                                transaction: transaction.id,
                                            })}
                                            method="delete"
                                            onSuccess={() => rootProps.onOpenChange?.(false)}
                                        />
                                    }
                                >
                                    <Button
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        variant="ghost"
                                    >
                                        {t("Delete")}
                                    </Button>
                                </ConfirmAction>
                            </div>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            )}
        </Modal>
    );
}

function CopyIdButton({ value }: { value: string }) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the identifier was just copied.
     */
    const [copied, setCopied] = useState(false);

    return (
        <Button
            aria-label={copied ? t("Copied") : t("Click to copy")}
            onClick={() => {
                void navigator.clipboard.writeText(value).then(
                    () => {
                        setCopied(true);
                        window.setTimeout(() => setCopied(false), 1500);
                    },
                    () => undefined,
                );
            }}
            size="icon-xs"
            type="button"
            variant="ghost"
        >
            {copied ? <CheckIcon /> : <CopyIcon />}
        </Button>
    );
}

/**
 * Strip padded decimal:18 zeros so a catalog rate can sit in a sentence.
 */
function trimTrailingZeros(value: string): string {
    if (!value.includes(".")) {
        return value;
    }

    return value.replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
}

/**
 * Format a rate for display (grouping, no currency symbol, keep significant fraction digits).
 */
function formatRate(rate: string, locale: string): string {
    const trimmed = trimTrailingZeros(rate);
    const unsigned = trimmed.startsWith("-") ? trimmed.slice(1) : trimmed;
    const [integerPart = "0", fractionPart = ""] = unsigned.split(".");
    const formattedInteger = new Intl.NumberFormat(locale, {
        maximumFractionDigits: 0,
        useGrouping: true,
    }).format(BigInt(integerPart.replace(/^0+(?=\d)/, "") || "0"));
    const decimal =
        new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === "decimal")?.value ?? ".";
    const formatted = fractionPart === "" ? formattedInteger : `${formattedInteger}${decimal}${fractionPart}`;

    return trimmed.startsWith("-") ? `-${formatted}` : formatted;
}

/**
 * Divide two major-unit decimal strings and return the quotient with a fixed scale.
 */
function divideDecimals(dividend: string, divisor: string, places: number): string | null {
    const numerator = parseDecimal(dividend);
    const denominator = parseDecimal(divisor);

    if (denominator.digits === 0n) {
        return null;
    }

    const exponent = places + denominator.scale - numerator.scale;
    const scaledNumerator = exponent >= 0 ? numerator.digits * 10n ** BigInt(exponent) : numerator.digits;
    const scaledDenominator = exponent >= 0 ? denominator.digits : denominator.digits * 10n ** BigInt(-exponent);
    const quotient = scaledNumerator / scaledDenominator;
    const negative = numerator.negative !== denominator.negative;
    const asString = quotient.toString().padStart(places + 1, "0");
    const integer = asString.slice(0, asString.length - places) || "0";
    const fraction = asString.slice(asString.length - places);

    return `${negative ? "-" : ""}${integer}.${fraction}`;
}

/**
 * Split a major-unit decimal string into a sign, integer digits, and scale.
 */
function parseDecimal(value: string): { digits: bigint; negative: boolean; scale: number } {
    const unsigned = value.startsWith("-") ? value.slice(1) : value;
    const [integerPart = "0", fractionPart = ""] = unsigned.split(".");

    return {
        digits: BigInt((integerPart.replace(/^0+(?=\d)/, "") || "0") + fractionPart),
        negative: value.startsWith("-"),
        scale: fractionPart.length,
    };
}
