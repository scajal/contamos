import { useLaravelReactI18n } from "laravel-react-i18n";
import { m, useReducedMotion } from "motion/react";
import { useEffect, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatAmount } from "@/lib/utils";

type Currency = "EUR" | "USD" | "ARS" | "BTC";

interface DemoTransaction {
    currency: Currency;
    symbol: string;
    decimalPlaces: number;
    tag: string;
    label: string;
    amount: string;
    kind: "income" | "expense";
}

const CURRENCIES: Array<{ currency: Currency; symbol: string; decimalPlaces: number }> = [
    { currency: "EUR", symbol: "€", decimalPlaces: 2 },
    { currency: "USD", symbol: "$", decimalPlaces: 2 },
    { currency: "ARS", symbol: "$", decimalPlaces: 2 },
    { currency: "BTC", symbol: "₿", decimalPlaces: 8 },
];

const TRANSACTIONS: Array<DemoTransaction> = [
    {
        currency: "EUR",
        symbol: "€",
        decimalPlaces: 2,
        tag: "Rent",
        label: "Apartment rent",
        amount: "-950.00",
        kind: "expense",
    },
    {
        currency: "USD",
        symbol: "$",
        decimalPlaces: 2,
        tag: "Salary",
        label: "Monthly salary",
        amount: "3200.00",
        kind: "income",
    },
    {
        currency: "ARS",
        symbol: "$",
        decimalPlaces: 2,
        tag: "Groceries",
        label: "Supermarket",
        amount: "-42300.00",
        kind: "expense",
    },
    {
        currency: "BTC",
        symbol: "₿",
        decimalPlaces: 8,
        tag: "Savings",
        label: "DCA buy",
        amount: "0.00042000",
        kind: "income",
    },
];

/**
 * Compute the running balance per currency after the given
 * number of demo transactions have been applied.
 */
const balancesAfter = (count: number): Record<Currency, number> => {
    const balances: Record<Currency, number> = { EUR: 1250.4, USD: 4800, ARS: -12000, BTC: 0.0031 };

    for (const transaction of TRANSACTIONS.slice(0, count)) {
        balances[transaction.currency] += Number(transaction.amount);
    }

    return balances;
};

/**
 * The hero's one kinetic object: a dark ledger card where demo
 * transactions across EUR, USD, ARS and BTC type in one by one while
 * per-currency balance chips re-settle. Renders statically for
 * reduced-motion visitors and is keyboard-replayable.
 */
export const LiveLedger = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * How many demo transactions have appeared so far.
     */
    const [visible, setVisible] = useState(reducedMotion ? TRANSACTIONS.length : 0);

    /**
     * A key that, when changed, restarts the reveal sequence.
     */
    const [run, setRun] = useState(0);

    useEffect(() => {
        if (reducedMotion) {
            setVisible(TRANSACTIONS.length);

            return;
        }

        setVisible(0);

        const timers = TRANSACTIONS.map((_, index) =>
            window.setTimeout(() => setVisible(index + 1), 900 + index * 1400),
        );

        return () => {
            timers.forEach(window.clearTimeout);
        };
    }, [reducedMotion, run]);

    const balances = balancesAfter(visible);

    return (
        <div
            className="relative min-h-[420px] overflow-hidden rounded-2xl bg-zinc-950 p-6 text-zinc-50 shadow-2xl"
            role="img"
            aria-label={t("Live ledger, showing example transactions across currencies.")}
        >
            <div aria-hidden="true" className="flex flex-col gap-6">
                {/** Balance chips */}
                <div className="flex flex-wrap gap-2">
                    {CURRENCIES.map(({ currency, symbol, decimalPlaces }) => (
                        <Badge
                            key={currency}
                            variant="outline"
                            className="border-zinc-800 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-200 tabular-nums"
                        >
                            <span className="mr-1.5 text-zinc-500">{currency}</span>
                            {balances[currency] < 0 ? "-" : ""}
                            {formatAmount(String(balances[currency]), "en", { symbol, decimal_places: decimalPlaces })}
                        </Badge>
                    ))}
                </div>

                {/** Transaction rows */}
                <div className="flex flex-col gap-2">
                    {TRANSACTIONS.map((transaction, index) => (
                        <m.div
                            key={`${transaction.currency}-${index}-${run}`}
                            initial={reducedMotion ? false : { opacity: 0, y: 12 }}
                            animate={index < visible ? { opacity: 1, y: 0 } : { opacity: 0, y: 12 }}
                            transition={{ type: "spring", stiffness: 260, damping: 24 }}
                            className="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-900/60 px-4 py-3"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="rounded-full bg-zinc-800 px-2 py-0.5 text-xs text-zinc-400">
                                    {transaction.tag}
                                </span>
                                <span className="truncate text-sm text-zinc-300">{transaction.label}</span>
                            </div>
                            <span
                                className={`shrink-0 text-sm font-medium tabular-nums ${transaction.kind === "income" ? "text-primary" : "text-zinc-400"}`}
                            >
                                {transaction.kind === "income" ? "+" : "-"}{" "}
                                {formatAmount(transaction.amount, "en", {
                                    symbol: transaction.symbol,
                                    decimal_places: transaction.decimalPlaces,
                                })}
                            </span>
                        </m.div>
                    ))}
                </div>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setRun((value) => value + 1)}
                className="absolute top-4 right-4 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100"
            >
                {t("Replay")}
            </Button>
        </div>
    );
};
