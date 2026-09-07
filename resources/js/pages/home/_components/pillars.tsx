import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";

const PILLARS = [
    {
        title: "Balances per currency, not a blended guess.",
        fragment: (
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">EUR</span>
                    <span className="font-medium tabular-nums">€ 1,250.40</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">USD</span>
                    <span className="font-medium tabular-nums">$ 4,800.00</span>
                </div>
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">BTC</span>
                    <span className="font-medium tabular-nums">₿ 0.00310000</span>
                </div>
            </div>
        ),
    },
    {
        title: "One ledger for the whole household.",
        fragment: (
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="min-w-0 truncate">Apartment rent</span>
                    <span className="text-muted-foreground shrink-0 tabular-nums">- € 950.00</span>
                </div>
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="min-w-0 truncate">Monthly salary</span>
                    <span className="text-primary shrink-0 tabular-nums">+ $ 3,200.00</span>
                </div>
            </div>
        ),
    },
    {
        title: "Tags that answer your questions.",
        fragment: (
            <div className="flex flex-wrap gap-2">
                <Badge>Groceries</Badge>
                <Badge variant="secondary">Rent</Badge>
                <Badge variant="secondary">Savings</Badge>
                <Badge variant="secondary">Salary</Badge>
            </div>
        ),
    },
] as const;

/**
 * The three pillars, as an editorial two-column list with small
 * product fragments framed like real UI instead of an identical
 * card grid.
 */
export const Pillars = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <h2 className="font-heading max-w-[30ch] text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Built for the way families really handle money")}
            </h2>

            <div className="mt-12 flex flex-col divide-y">
                {PILLARS.map((pillar) => (
                    <div key={pillar.title} className="grid gap-6 py-10 md:grid-cols-2 md:items-center">
                        <p className="max-w-[36ch] text-xl font-medium text-balance md:text-2xl">{t(pillar.title)}</p>
                        <div className="bg-muted/30 rounded-2xl border p-5">{pillar.fragment}</div>
                    </div>
                ))}
            </div>
        </section>
    );
};
