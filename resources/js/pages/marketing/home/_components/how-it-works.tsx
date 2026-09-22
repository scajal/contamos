import { useLaravelReactI18n } from "laravel-react-i18n";
import { BanknoteIcon, CalendarRangeIcon, PencilLineIcon } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";

/**
 * Three steps, in the order someone actually takes them. Each one is
 * something the product does on day one, so a visitor can picture the
 * first evening rather than a roadmap.
 */
const STEPS: Array<{ body: string; icon: LucideIcon; title: string }> = [
    {
        body: "The bank, the cash at home, the crypto wallet. Turn on the currencies each one holds and it keeps a balance for every one of them.",
        icon: BanknoteIcon,
        title: "Name the places your money lives.",
    },
    {
        body: "Amount first, then the account. The date, the note and the tags are one tap away, and the rate of that day is saved with it.",
        icon: PencilLineIcon,
        title: "Write down what came in and what went out.",
    },
    {
        body: "At the end of the month you see what entered, what left, and where it went by tag, in each currency on its own.",
        icon: CalendarRangeIcon,
        title: "Look back together.",
    },
];

/**
 * The bridge between what the product is and what it costs: how using
 * it actually goes. It sits on the page's own background so the dark
 * feature block above it reads as the one block of colour.
 */
export const HowItWorks = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <section id="how-it-works" className="bg-background relative scroll-mt-28">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="flex max-w-3xl flex-col gap-5"
                >
                    <h2 className="font-heading text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("Three steps, and the first one takes a minute.")}
                    </h2>
                    <p className="text-muted-foreground max-w-[54ch] text-lg">
                        {t(
                            "There is nothing to set up, no chart of accounts to learn and no categories to design: the short list comes with the ledger. You write down money as it moves and the ledger keeps the rest.",
                        )}
                    </p>
                </m.div>

                <ol className="mt-14 grid gap-4 sm:mt-16 lg:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <m.li
                            key={step.title}
                            initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            viewport={{ once: true, margin: "-60px" }}
                            transition={{ duration: 0.55, ease: "easeOut", delay: index * 0.08 }}
                            className="bg-card flex flex-col gap-4 rounded-2xl border p-6 sm:p-7"
                        >
                            <div className="flex items-center gap-3">
                                <span
                                    aria-hidden="true"
                                    className="bg-muted text-foreground grid size-11 shrink-0 place-items-center rounded-xl"
                                >
                                    <step.icon className="size-5" strokeWidth={1.75} />
                                </span>
                                <span className="text-muted-foreground text-sm font-medium tabular-nums">
                                    {String(index + 1).padStart(2, "0")}
                                </span>
                            </div>
                            <h3 className="font-heading text-lg leading-snug font-semibold tracking-tight text-balance">
                                {t(step.title)}
                            </h3>
                            <p className="text-muted-foreground max-w-[46ch] text-sm leading-relaxed">{t(step.body)}</p>
                        </m.li>
                    ))}
                </ol>
            </div>
        </section>
    );
};
