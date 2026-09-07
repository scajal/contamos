import { useLaravelReactI18n } from "laravel-react-i18n";
import { m, useReducedMotion } from "motion/react";

import { cn } from "@/lib/utils";

import { Reveal } from "./reveal";

const RECEIPTS = [
    { tag: "Rent", amount: "€ 950.00", rotate: "-rotate-6", offset: "" },
    { tag: "Salary", amount: "$ 3,200.00", rotate: "rotate-3", offset: "mt-8 sm:mt-10" },
    { tag: "Savings", amount: "₿ 0.00042000", rotate: "-rotate-2", offset: "-mt-2 sm:mt-2" },
] as const;

/**
 * The problem statement: a full-bleed muted band pairing a short,
 * punchy statement with a small pile of scattered, mismatched
 * currency amounts that literalize "messy" — no two receipts align.
 * The receipts drop into place, staggered, as the section scrolls
 * into view.
 */
export const Problem = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <section id="product" className="bg-muted/40 border-y">
            <div className="mx-auto grid max-w-6xl gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:items-center lg:gap-16">
                <Reveal as="div" className="max-w-[32ch] text-2xl leading-snug font-medium text-balance md:text-3xl">
                    {t(
                        "Rent in euros. Salary in dollars. Savings in bitcoin. Blended into one number that hides what's really going on.",
                    )}
                </Reveal>

                <div aria-hidden="true" className="flex flex-wrap items-start justify-center gap-4 sm:justify-start">
                    {RECEIPTS.map((receipt, index) => (
                        <m.div
                            key={receipt.tag}
                            initial={reducedMotion ? false : { opacity: 0, y: -24, scale: 0.9 }}
                            whileInView={{ opacity: 1, y: 0, scale: 1 }}
                            viewport={{ once: true, margin: "-80px" }}
                            transition={{ duration: 0.5, ease: "easeOut", delay: index * 0.12 }}
                            className={cn(
                                "bg-background w-40 rounded-xl border p-4 shadow-md transition-transform duration-300 hover:rotate-0",
                                receipt.rotate,
                                receipt.offset,
                            )}
                        >
                            <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs">
                                {receipt.tag}
                            </span>
                            <p className="mt-2 text-lg font-semibold tabular-nums">{receipt.amount}</p>
                        </m.div>
                    ))}
                </div>
            </div>
        </section>
    );
};
