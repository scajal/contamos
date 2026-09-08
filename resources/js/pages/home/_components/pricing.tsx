import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { login } from "@/routes";
import type { Plans } from "@/types/app/modules/marketing/plans";

import { GradientRibbon } from "./gradient-ribbon";

type Cycle = "monthly" | "yearly";

interface PricingProps {
    plans: Plans;
    dashboardUrl: string | null;
}

/**
 * The plans, priced from Paddle rather than from a number typed into
 * this file. Sits on the page background so it reads as a break from
 * the always-dark features section above it.
 */
export const Pricing = ({ plans, dashboardUrl }: PricingProps) => {
    /**
     * Translate the provided string to the current locale. The plan
     * limits are counts, so they go through `tChoice` to get the right
     * plural form in both languages.
     */
    const { t, tChoice } = useLaravelReactI18n();

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The billing cycle being shown.
     */
    const [cycle, setCycle] = useState<Cycle>("monthly");

    const { free, pro } = plans;
    const price = cycle === "monthly" ? pro.monthly : pro.yearly;

    /**
     * What a year on the yearly cycle saves against twelve monthly
     * charges, as a whole percentage — computed from the amounts Paddle
     * returned, so it can never contradict the prices on show.
     */
    const saving =
        pro.monthlyAmount && pro.yearlyAmount
            ? Math.round((1 - pro.yearlyAmount / (pro.monthlyAmount * 12)) * 100)
            : null;

    /**
     * Both plans start the same way: there is nothing to buy before
     * signing in, so each card sends the visitor to the same place.
     */
    const href = dashboardUrl ?? login.url();

    const freeFeatures = [
        tChoice(":count account|:count accounts", free.accounts ?? 1),
        tChoice(":count person|:count people", free.members ?? 1),
        tChoice(":count transaction per month|:count transactions per month", free.transactions ?? 100),
        t("Every currency, fiat and crypto"),
        t("Tags, filters and search"),
    ];

    const proFeatures = [
        t("Unlimited accounts"),
        t("Unlimited people in the household"),
        t("Unlimited transactions"),
        t("Everything in the free plan"),
        t("Cancel whenever you want"),
    ];

    return (
        <section id="pricing" className="bg-background relative scroll-mt-28 overflow-hidden">
            <GradientRibbon />

            <div className="relative mx-auto max-w-5xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="flex flex-col items-center gap-6 text-center"
                >
                    <span className="text-muted-foreground text-xs font-medium tracking-[0.2em] uppercase">
                        {t("Pricing")}
                    </span>
                    <h2 className="font-heading max-w-[18ch] text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("Start free. Pay only when your household outgrows it.")}
                    </h2>

                    <CycleToggle cycle={cycle} onChange={setCycle} saving={saving} />
                </m.div>

                <div className="mt-14 grid gap-4 sm:mt-16 lg:grid-cols-2 lg:gap-0">
                    <PlanCard
                        name={t("Free")}
                        price={t("$0")}
                        cadence={t("forever")}
                        description={t("Enough to keep one person's money honest.")}
                        features={freeFeatures}
                        href={href}
                        cta={t("Start")}
                        variant="outline"
                        className="lg:pr-12"
                        delay={0}
                        reducedMotion={reducedMotion}
                    />

                    <PlanCard
                        name={t("Pro")}
                        price={price ?? t("See pricing")}
                        cadence={price ? (cycle === "monthly" ? t("per month") : t("per year")) : null}
                        description={t("For a household that shares everything, in every currency.")}
                        features={proFeatures}
                        href={href}
                        cta={t("Start")}
                        badge={t("Recommended")}
                        featured
                        delay={0.1}
                        reducedMotion={reducedMotion}
                    />
                </div>

                <p className="text-muted-foreground mt-8 text-center text-sm">{t("Free plan. No card required.")}</p>
            </div>
        </section>
    );
};

interface CycleToggleProps {
    cycle: Cycle;
    onChange: (cycle: Cycle) => void;
    saving: number | null;
}

/**
 * The monthly/yearly switch, built like the header's language toggle so
 * the site only ever has one kind of segmented control.
 */
const CycleToggle = ({ cycle, onChange, saving }: CycleToggleProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const options: Array<{ value: Cycle; label: string }> = [
        { value: "monthly", label: t("Monthly") },
        { value: "yearly", label: t("Yearly") },
    ];

    return (
        <div className="bg-muted/70 flex items-center rounded-full p-1">
            {options.map(({ value, label }) => (
                <button
                    key={value}
                    type="button"
                    onClick={() => onChange(value)}
                    aria-pressed={cycle === value}
                    className={cn(
                        "flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-colors",
                        cycle === value
                            ? "bg-background text-foreground shadow-sm"
                            : "text-muted-foreground hover:text-foreground",
                    )}
                >
                    {label}
                    {value === "yearly" && saving !== null && saving > 0 && (
                        <span className="bg-success/15 text-success rounded-full px-1.5 py-0.5 text-[11px] font-semibold">
                            {t("-:percent%", { percent: String(saving) })}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
};

interface PlanCardProps {
    name: string;
    price: string;
    cadence: string | null;
    description: string;
    features: Array<string>;
    href: string;
    cta: string;
    badge?: string;
    featured?: boolean;
    variant?: "outline";
    className?: string;
    delay: number;
    reducedMotion: boolean | null;
}

/**
 * One plan. The featured card carries the primary ring and the solid
 * call to action, so the pair reads as a recommendation rather than as
 * two equal options. Its depth comes from the overlap and the shadow
 * alone: a blurred halo behind it would sit *above* the card, because
 * lifting it with `z-10` makes the card its own stacking context.
 */
const PlanCard = ({
    name,
    price,
    cadence,
    description,
    features,
    href,
    cta,
    badge,
    featured,
    variant,
    className,
    delay,
    reducedMotion,
}: PlanCardProps) => (
    <m.article
        initial={reducedMotion ? false : { opacity: 0, y: 24 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: "-60px" }}
        transition={{ duration: 0.55, ease: "easeOut", delay }}
        className={cn(
            "bg-card relative flex flex-col gap-6 rounded-3xl border p-8",
            // The featured card rides over its neighbour: taller, pulled
            // left across the closed grid gap, and lifted above it.
            featured && "border-primary/40 shadow-2xl lg:z-10 lg:-my-4 lg:-ml-6 lg:p-9",
            className,
        )}
    >
        <div className="flex items-center justify-between gap-3">
            <h3 className="font-heading text-xl font-semibold tracking-tight">{name}</h3>
            {badge && (
                <span className="bg-primary text-primary-foreground rounded-full px-3 py-1 text-xs font-medium">
                    {badge}
                </span>
            )}
        </div>

        <div className="flex items-baseline gap-2">
            <span className="font-heading text-4xl font-semibold tracking-tight tabular-nums">{price}</span>
            {cadence && <span className="text-muted-foreground text-sm">{cadence}</span>}
        </div>

        <p className="text-muted-foreground text-sm leading-relaxed">{description}</p>

        <ul className="flex flex-col gap-3">
            {features.map((feature) => (
                <li key={feature} className="flex items-start gap-3 text-sm">
                    <CheckIcon
                        aria-hidden="true"
                        className={cn("mt-0.5 size-4 shrink-0", featured ? "text-primary" : "text-muted-foreground")}
                        strokeWidth={2.5}
                    />
                    {feature}
                </li>
            ))}
        </ul>

        <Button
            className="mt-auto w-full"
            nativeButton={false}
            render={<a href={href} />}
            size="lg"
            variant={variant}
        >
            {cta}
        </Button>
    </m.article>
);
