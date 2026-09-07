import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";
import type { PlanSummary } from "@/types/app/modules/billing/plan-summary";

interface PlansProps {
    plans: Array<PlanSummary>;
    dashboardUrl: string | null;
}

/**
 * The Free vs. Pro comparison, driven by the Plan/Feature enums.
 * No prices: pricing is shown at checkout.
 */
export const Plans = ({ plans, dashboardUrl }: PlansProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section id="plans" className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <h2 className="font-heading max-w-[26ch] text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Start free, upgrade when your household grows")}
            </h2>

            <div className="mt-12 grid gap-6 md:grid-cols-2">
                {plans.map((plan) => (
                    <div key={plan.key} className="flex flex-col gap-6 rounded-2xl border p-8">
                        <div>
                            <h3 className="font-heading text-xl font-semibold">{t(plan.label)}</h3>
                        </div>

                        <ul className="flex flex-col gap-3">
                            {plan.features.map((feature) => (
                                <li key={feature.key} className="flex items-center gap-2 text-sm">
                                    <CheckIcon className="text-primary size-4 shrink-0" aria-hidden="true" />
                                    <span>
                                        {t(feature.label)}: {feature.limit === null ? t("Unlimited") : feature.limit}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <Button
                            nativeButton={false}
                            render={<a href={dashboardUrl ?? login.url()} />}
                            size="lg"
                            variant={plan.key === "pro" ? "outline" : "default"}
                            className="mt-auto"
                        >
                            {t(dashboardUrl ? "Go to dashboard" : "Start free")}
                        </Button>
                    </div>
                ))}
            </div>

            <p className="text-muted-foreground mt-6 text-sm">{t("Pro pricing is shown at checkout.")}</p>
        </section>
    );
};
