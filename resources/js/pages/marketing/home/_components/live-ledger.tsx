import NumberFlow from "@number-flow/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    AnimatePresence,
    m,
    useMotionTemplate,
    useMotionValue,
    useReducedMotion,
    useSpring,
    useTransform,
} from "motion/react";
import { useEffect, useState } from "react";
import type { MouseEvent as ReactMouseEvent } from "react";

import { Badge } from "@/components/ui/badge";
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

/** One transaction currently on the ledger, keyed so repeats animate apart. */
interface LedgerEntry {
    key: number;
    transaction: DemoTransaction;
}

const CURRENCIES: Array<{ currency: Currency; symbol: string; decimalPlaces: number }> = [
    { currency: "EUR", symbol: "€", decimalPlaces: 2 },
    { currency: "USD", symbol: "$", decimalPlaces: 2 },
    { currency: "BTC", symbol: "₿", decimalPlaces: 8 },
];

const OPENING_BALANCES: Record<Currency, number> = { EUR: 1250.4, USD: 4800, BTC: 0.0031 };

/** How many rows the ledger holds at most, and the fewest it drops to. */
const MAX_ROWS = 4;
const MIN_ROWS = 2;

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
    {
        currency: "EUR",
        symbol: "€",
        decimalPlaces: 2,
        tag: "Trip",
        label: "Train to Porto",
        amount: "-64.20",
        kind: "expense",
    },
    {
        currency: "USD",
        symbol: "$",
        decimalPlaces: 2,
        tag: "Freelance",
        label: "Invoice #204",
        amount: "780.00",
        kind: "income",
    },
    {
        currency: "EUR",
        symbol: "€",
        decimalPlaces: 2,
        tag: "Bills",
        label: "Electricity",
        amount: "-112.75",
        kind: "expense",
    },
    {
        currency: "BTC",
        symbol: "₿",
        decimalPlaces: 8,
        tag: "Savings",
        label: "Weekly buy",
        amount: "0.00018000",
        kind: "income",
    },
];

/**
 * The running balance per currency once every entry on the ledger has
 * been applied. Removing an entry therefore puts the balance back
 * exactly where it was, the same way deleting a transaction does in the
 * app itself.
 */
const balancesOf = (entries: Array<LedgerEntry>): Record<Currency, number> => {
    const balances: Record<Currency, number> = { ...OPENING_BALANCES };

    for (const { transaction } of entries) {
        balances[transaction.currency] += Number(transaction.amount);
    }

    return balances;
};

/**
 * Pick a random item from the list.
 */
const sample = <T,>(items: Array<T>): T => items[Math.floor(Math.random() * items.length)];

/**
 * The hero's one kinetic object: a dark ledger card that never settles.
 * Transactions arrive and are taken away again at random, forever, with
 * the per-currency chips rolling to each new total. Renders as a still
 * ledger for reduced-motion visitors. On pointer devices the card tilts
 * and glows toward the cursor.
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
     * The transactions currently on the ledger. Seeded deterministically
     * so the server and the client agree on the first paint; the loop
     * only starts randomising once mounted.
     */
    const [entries, setEntries] = useState<Array<LedgerEntry>>(() =>
        TRANSACTIONS.slice(0, MAX_ROWS).map((transaction, key) => ({ key, transaction })),
    );

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
            return;
        }

        let timer: number;
        let nextKey = MAX_ROWS;

        // One step of the loop: add a transaction or take one away, then
        // schedule the next step after an uneven pause so the ledger
        // never falls into an obvious rhythm.
        const step = () => {
            setEntries((current) => {
                const adding =
                    current.length <= MIN_ROWS || (current.length < MAX_ROWS && Math.random() < 0.55);

                if (!adding) {
                    const victim = sample(current);

                    return current.filter((entry) => entry.key !== victim.key);
                }

                // Only offer transactions that are not already on the
                // ledger, so a row never appears twice.
                const present = new Set(current.map((entry) => entry.transaction.label));
                const available = TRANSACTIONS.filter((transaction) => !present.has(transaction.label));

                if (available.length === 0) {
                    return current;
                }

                return [{ key: nextKey++, transaction: sample(available) }, ...current];
            });

            timer = window.setTimeout(step, 1300 + Math.random() * 1100);
        };

        timer = window.setTimeout(step, 1400);

        return () => window.clearTimeout(timer);
    }, [reducedMotion]);

    const balances = balancesOf(entries);

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
                            <span className="mr-1.5 text-zinc-400">{currency}</span>
                            <NumberFlow
                                value={balances[currency]}
                                locales="en"
                                format={{
                                    minimumFractionDigits: decimalPlaces,
                                    maximumFractionDigits: decimalPlaces,
                                }}
                                prefix={`${symbol} `}
                                spinTiming={{ duration: 900, easing: "cubic-bezier(0.16, 1, 0.3, 1)" }}
                                transformTiming={{ duration: 600, easing: "cubic-bezier(0.16, 1, 0.3, 1)" }}
                                willChange
                            />
                        </Badge>
                    ))}
                </div>

                {/**
                 * Transaction rows. The height is reserved for a full
                 * ledger so the card — and the hero around it — never
                 * resizes as rows come and go. Each row animates its own
                 * height rather than using layout projection, which the
                 * page's `domAnimation` feature bundle does not carry.
                 *
                 * The gap between rows is top padding inside each
                 * animated wrapper, not a flex gap or a margin, so it
                 * collapses along with the row's height instead of
                 * leaving a hole behind. The container pulls the first
                 * row's padding back off the top.
                 */}
                <div className="-mt-2 flex min-h-[236px] flex-col">
                    <AnimatePresence initial={false}>
                        {entries.map(({ key, transaction }) => (
                            <m.div
                                key={key}
                                initial={reducedMotion ? false : { opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: "auto" }}
                                exit={{ opacity: 0, height: 0 }}
                                transition={{ duration: 0.42, ease: [0.16, 1, 0.3, 1] }}
                                className="overflow-hidden"
                            >
                                <div className="pt-2">
                                    <div className="flex items-center justify-between gap-3 rounded-xl border border-zinc-800 bg-zinc-900/60 px-4 py-3">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="rounded-full bg-zinc-800 px-2 py-0.5 text-xs text-zinc-400">
                                                {transaction.tag}
                                            </span>
                                            <span className="truncate text-sm text-zinc-300">
                                                {transaction.label}
                                            </span>
                                        </div>
                                        {/* rose-400, not rose-500: the 500 lands at 4.13:1 on the row fill, under AA. */}
                                        <span
                                            className={cn(
                                                "shrink-0 text-sm font-medium tabular-nums",
                                                transaction.kind === "income" ? "text-green-500" : "text-rose-400",
                                            )}
                                        >
                                            {transaction.kind === "income" ? "+" : "-"}{" "}
                                            {formatAmount(transaction.amount, "en", {
                                                symbol: transaction.symbol,
                                                decimal_places: transaction.decimalPlaces,
                                            })}
                                        </span>
                                    </div>
                                </div>
                            </m.div>
                        ))}
                    </AnimatePresence>
                </div>
            </div>
        </m.div>
    );
};
