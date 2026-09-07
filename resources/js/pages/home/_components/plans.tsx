import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { login } from "@/routes";
import type { PlanSummary } from "@/types/app/modules/billing/plan-summary";

import { Reveal } from "./reveal";
import { TiltCard } from "./tilt-card";

interface PlansProps {
    plans: Array<PlanSummary>;
    dashboardUrl: string | null;
}

/**
 * The Free vs. Pro comparison, driven by the Plan/Feature enums.
 * No prices: pricing is shown at checkout. Pro reads as the dark,
 * featured card so it doesn't fall back to a same-size white twin
 * of Free. Both cards tilt gently toward the cursor and fade in as
 * the section scrolls into view.
 */
export const Plans = ({ plans, dashboardUrl }: PlansProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section id="plans" className="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <h2 className="font-heading max-w-[26ch] text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Start free, upgrade when your household grows")}
            </h2>

            <div className="mt-12 grid gap-6 md:grid-cols-2">
                {plans.map((plan, index) => {
                    const isPro = plan.key === "pro";

                    return (
                        <Reveal key={plan.key} delay={index * 0.1}>
                            <TiltCard
                                className={cn(
                                    "flex h-full flex-col gap-6 p-8 shadow-lg",
                                    isPro && "border-primary/50 bg-card ring-primary/20 ring-1",
                                )}
                            >
                                {!isPro && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute top-6 right-6 flex size-16 rotate-12 items-center justify-center rounded-full border-2 border-dashed border-rose-500 text-[0.6rem] font-bold tracking-wide text-rose-500 uppercase"
                                    >
                                        {t("No card")}
                                    </span>
                                )}

                                <h3 className="font-heading text-xl font-semibold">{t(plan.label)}</h3>

                                <ul className="flex flex-col gap-3">
                                    {plan.features.map((feature) => (
                                        <li key={feature.key} className="flex items-center gap-2 text-sm">
                                            <CheckIcon className="text-primary size-4 shrink-0" aria-hidden="true" />
                                            <span>
                                                {t(feature.label)}:{" "}
                                                {feature.limit === null ? t("Unlimited") : feature.limit}
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                <Button
                                    nativeButton={false}
                                    render={<a href={dashboardUrl ?? login.url()} />}
                                    size="lg"
                                    className={cn(
                                        "mt-auto",
                                        isPro && "bg-primary text-primary-foreground hover:bg-primary/80",
                                    )}
                                >
                                    {t(dashboardUrl ? "Go to dashboard" : "Start free")}
                                </Button>
                            </TiltCard>
                        </Reveal>
                    );
                })}
            </div>

            <p className="text-muted-foreground mt-6 text-sm">{t("Pro pricing is shown at checkout.")}</p>
        </section>
    );
};
