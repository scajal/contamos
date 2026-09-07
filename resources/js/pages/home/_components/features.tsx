import { useLaravelReactI18n } from "laravel-react-i18n";
import { CoinsIcon, RefreshCwIcon, SearchIcon, TagsIcon, Undo2Icon, UsersIcon } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";

import { cn } from "@/lib/utils";

interface Feature {
    icon: LucideIcon;
    title: string;
    body: string;
    /**
     * The card's share of the six-column bento: a full-width opener,
     * then a row of thirds and a row of halves, so nothing is left over.
     */
    span: string;
    /** Full-width cards read better with the copy beside the heading. */
    banner?: boolean;
}

const FEATURES: Array<Feature> = [
    {
        icon: CoinsIcon,
        title: "Accounts that hold more than one currency.",
        body: "Bank, cash or crypto — each account keeps your euros, dollars and bitcoin side by side, with its own balance for each one.",
        span: "sm:col-span-2 lg:col-span-6",
        banner: true,
    },
    {
        icon: UsersIcon,
        title: "One ledger, the whole household.",
        body: "Invite whoever lives with you by email. Everyone writes to the same ledger and sees who recorded what.",
        span: "lg:col-span-2",
    },
    {
        icon: TagsIcon,
        title: "Tags, not rigid categories.",
        body: "Label a movement the way you actually think about it. Rent, the trip, the dog — the vocabulary is yours.",
        span: "lg:col-span-2",
    },
    {
        icon: RefreshCwIcon,
        title: "The rate, saved exactly as it was.",
        body: "Every transaction stores the exchange rate of the day it happened, synced daily. Months later it still reads true.",
        span: "lg:col-span-2",
    },
    {
        icon: SearchIcon,
        title: "Find anything in seconds.",
        body: "Filter by account, currency, tag or date — or just search. The list keeps loading as you scroll.",
        span: "lg:col-span-3",
    },
    {
        icon: Undo2Icon,
        title: "Delete without breaking anything.",
        body: "Remove a transaction and the balance goes back exactly where it was. No manual fixing afterwards.",
        span: "lg:col-span-3",
    },
];

/**
 * The second act, in its own colour so it reads as a clean break from
 * the hero's stage: what the app actually does, one claim per card,
 * each of them something the product really ships.
 *
 * It stays dark in both themes on purpose — against a light hero it is
 * the page's one block of colour, and against a dark one it deepens.
 * The palette is therefore literal rather than tokenised.
 */
export const Features = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <section id="features" className="relative bg-zinc-950 text-zinc-50">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="flex max-w-3xl flex-col gap-5"
                >
                    <span className="text-xs font-medium tracking-[0.2em] text-zinc-500 uppercase">
                        {t("What you get")}
                    </span>
                    <h2 className="font-heading text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("Built around how your money really works.")}
                    </h2>
                    <p className="max-w-[54ch] text-lg text-zinc-400">
                        {t(
                            "Not a spreadsheet with a nicer skin. Accounts that speak several currencies, a ledger the whole household writes to, and every rate kept exactly as it was.",
                        )}
                    </p>
                </m.div>

                <div className="mt-14 grid gap-4 sm:mt-16 sm:grid-cols-2 lg:grid-cols-6">
                    {FEATURES.map((feature, index) => (
                        <m.article
                            key={feature.title}
                            initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: "-60px" }}
                            transition={{ duration: 0.55, ease: "easeOut", delay: index * 0.07 }}
                            className={cn(
                                "group relative flex flex-col gap-4 rounded-2xl border border-zinc-800 bg-zinc-900 p-6 transition-colors duration-300 hover:border-zinc-600 sm:p-7",
                                feature.span,
                                feature.banner && "lg:flex-row lg:items-center lg:gap-10",
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className="grid size-11 shrink-0 place-items-center rounded-xl bg-zinc-800 text-zinc-100 transition-colors duration-300 group-hover:bg-zinc-50 group-hover:text-zinc-900"
                            >
                                <feature.icon className="size-5" strokeWidth={1.75} />
                            </span>

                            <div
                                className={cn(
                                    "flex flex-col gap-2",
                                    feature.banner && "lg:flex-row lg:items-baseline lg:gap-12",
                                )}
                            >
                                <h3
                                    className={cn(
                                        "font-heading text-lg leading-snug font-semibold tracking-tight text-balance",
                                        feature.banner && "lg:shrink-0 lg:basis-[20ch] lg:text-2xl",
                                    )}
                                >
                                    {t(feature.title)}
                                </h3>
                                <p className="max-w-[62ch] text-sm leading-relaxed text-zinc-400">
                                    {t(feature.body)}
                                </p>
                            </div>
                        </m.article>
                    ))}
                </div>
            </div>
        </section>
    );
};
