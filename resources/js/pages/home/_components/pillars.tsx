import { useLaravelReactI18n } from "laravel-react-i18n";

import { Badge } from "@/components/ui/badge";

const PILLARS = [
    {
        title: "Balances per currency, not a blended guess.",
        fragment: (
            <div className="flex gap-2">
                <Badge variant="outline" className="px-2.5 py-1 text-sm tabular-nums">
                    EUR 1,250.40
                </Badge>
                <Badge variant="outline" className="px-2.5 py-1 text-sm tabular-nums">
                    USD 4,800.00
                </Badge>
            </div>
        ),
    },
    {
        title: "One ledger for the whole household.",
        fragment: (
            <div className="flex items-center gap-3 rounded-xl border px-4 py-3">
                <span className="text-sm font-medium">Apartment rent</span>
                <span className="text-muted-foreground text-sm tabular-nums">- € 950.00</span>
            </div>
        ),
    },
    {
        title: "Tags that answer your questions.",
        fragment: (
            <div className="flex gap-2">
                <Badge>Groceries</Badge>
                <Badge variant="secondary">Rent</Badge>
                <Badge variant="secondary">Savings</Badge>
            </div>
        ),
    },
] as const;

/**
 * The three pillars, as an editorial two-column list with small
 * product fragments instead of an identical card grid.
 */
export const Pillars = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <h2 className="font-heading max-w-[30ch] text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Built for the way families really handle money")}
            </h2>

            <div className="mt-12 flex flex-col divide-y">
                {PILLARS.map((pillar) => (
                    <div key={pillar.title} className="grid gap-6 py-8 md:grid-cols-2 md:items-center">
                        <p className="max-w-[36ch] text-xl font-medium text-balance md:text-2xl">{t(pillar.title)}</p>
                        {pillar.fragment}
                    </div>
                ))}
            </div>
        </section>
    );
};
