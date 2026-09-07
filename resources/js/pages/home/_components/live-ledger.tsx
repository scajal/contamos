import { useLaravelReactI18n } from "laravel-react-i18n";
import { m, useMotionTemplate, useMotionValue, useReducedMotion, useSpring, useTransform } from "motion/react";
import { useEffect, useState } from "react";
import type { MouseEvent as ReactMouseEvent } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { cn, formatAmount } from "@/lib/utils";

type Currency = "EUR" | "USD" | "BTC";

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
        currency: "USD",
        symbol: "$",
        decimalPlaces: 2,
        tag: "Groceries",
        label: "Supermarket",
        amount: "-85.40",
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
    const balances: Record<Currency, number> = { EUR: 1250.4, USD: 4800, BTC: 0.0031 };

    for (const transaction of TRANSACTIONS.slice(0, count)) {
        balances[transaction.currency] += Number(transaction.amount);
    }

    return balances;
};

/**
 * The hero's one kinetic object: a dark ledger card where demo
 * transactions across EUR, USD and BTC type in one by one while
 * per-currency balance chips re-settle. Every row is present from the
 * first frame (as a dimmed placeholder) so the card never reads as
 * empty mid-sequence. On pointer devices the card tilts and glows
 * toward the cursor. Renders statically for reduced-motion visitors
 * and is keyboard-replayable.
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

    /**
     * The pointer's position over the card, normalized to 0-1,
     * driving a subtle tilt and a spotlight that follow the cursor.
     */
    const pointerX = useMotionValue(0.5);
    const pointerY = useMotionValue(0.5);
    const springs = { stiffness: 200, damping: 24 };
    const rotateX = useSpring(useTransform(pointerY, [0, 1], [6, -6]), springs);
    const rotateY = useSpring(useTransform(pointerX, [0, 1], [-6, 6]), springs);
    const spotlightX = useSpring(
        useTransform(pointerX, (value) => `${value * 100}%`),
        springs,
    );
    const spotlightY = useSpring(
        useTransform(pointerY, (value) => `${value * 100}%`),
        springs,
    );
    const spotlight = useMotionTemplate`radial-gradient(320px circle at ${spotlightX} ${spotlightY}, color-mix(in oklch, var(--primary) 25%, transparent), transparent 70%)`;

    /**
     * Whether the pointer is currently over the card, gating the
     * spotlight's visibility.
     */
    const [hovering, setHovering] = useState(false);

    /**
     * Track the pointer's position over the card.
     */
    const handlePointerMove = (event: ReactMouseEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        pointerX.set((event.clientX - rect.left) / rect.width);
        pointerY.set((event.clientY - rect.top) / rect.height);
    };

    /**
     * Reset the tilt and spotlight to their neutral position.
     */
    const handlePointerLeave = () => {
        setHovering(false);
        pointerX.set(0.5);
        pointerY.set(0.5);
    };

    useEffect(() => {
        if (reducedMotion) {
            setVisible(TRANSACTIONS.length);

            return;
        }

        setVisible(0);

        const timers = TRANSACTIONS.map((_, index) =>
            window.setTimeout(() => setVisible(index + 1), 500 + index * 1000),
        );

        return () => {
            timers.forEach(window.clearTimeout);
        };
    }, [reducedMotion, run]);

    const balances = balancesAfter(visible);

    return (
        <m.div
            className="relative min-h-[340px] overflow-hidden rounded-2xl bg-zinc-950 p-6 text-zinc-50 shadow-[0_24px_60px_-24px_rgb(0_0_0_/_0.6)] sm:p-7"
            style={
                reducedMotion
                    ? undefined
                    : { rotateX, rotateY, transformPerspective: 900, transformStyle: "preserve-3d" }
            }
            onMouseMove={reducedMotion ? undefined : handlePointerMove}
            onMouseEnter={reducedMotion ? undefined : () => setHovering(true)}
            onMouseLeave={reducedMotion ? undefined : handlePointerLeave}
            role="img"
            aria-label={t("Live ledger, showing example transactions across currencies.")}
        >
            <div
                aria-hidden="true"
                className="bg-primary/20 pointer-events-none absolute -top-24 -right-24 size-64 rounded-full blur-3xl"
            />

            {!reducedMotion && (
                <m.div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 rounded-2xl transition-opacity duration-300"
                    style={{ background: spotlight, opacity: hovering ? 1 : 0 }}
                />
            )}

            <div aria-hidden="true" className="relative flex flex-col gap-5">
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

                {/** Transaction rows: always present, dimmed until revealed */}
                <div className="flex flex-col gap-2">
                    {TRANSACTIONS.map((transaction, index) => {
                        const revealed = index < visible;

                        return (
                            <m.div
                                key={`${transaction.currency}-${index}-${run}`}
                                initial={reducedMotion ? false : { opacity: 0, y: 10 }}
                                animate={revealed ? { opacity: 1, y: 0 } : { opacity: 0.16, y: 0 }}
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
                                    className={cn(
                                        "shrink-0 text-sm font-medium tabular-nums",
                                        transaction.kind === "income" ? "text-green-500" : "text-rose-500",
                                    )}
                                >
                                    {transaction.kind === "income" ? "+" : "-"}{" "}
                                    {formatAmount(transaction.amount, "en", {
                                        symbol: transaction.symbol,
                                        decimal_places: transaction.decimalPlaces,
                                    })}
                                </span>
                            </m.div>
                        );
                    })}
                </div>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                onClick={() => setRun((value) => value + 1)}
                aria-label={t("Replay")}
                className="absolute top-6 right-6 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-100 sm:top-7 sm:right-7"
            >
                <RefreshIcon />
            </Button>
        </m.div>
    );
};

/**
 * A small refresh glyph for the replay control, drawn inline so the
 * ledger doesn't pull in a full icon set for one shape.
 */
const RefreshIcon = () => (
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth={1.5} className="size-4">
        <path d="M13.5 8a5.5 5.5 0 1 1-1.6-3.9" strokeLinecap="round" strokeLinejoin="round" />
        <path d="M13.5 2.5v3.6h-3.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
);
