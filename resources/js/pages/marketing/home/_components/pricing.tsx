import BorderBeam from "border-beam";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, CheckIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { perMonthPrice, yearlySaving } from "@/lib/billing";
import { cn } from "@/lib/utils";
import { PlanTable, type PlanTableRow } from "@/pages/billing/teams/subscriptions/_components/plan-table";
import { login } from "@/routes";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";

interface PricingProps {
    dashboardUrl: string | null;
    features: Array<PlanTableRow>;
    prices: Record<BillingCycle, PlanPrice>;
}

/**
 * What it costs, answered before the visitor has to ask. The limits
 * come from the same enum the app gates on and are rendered by the
 * same table the upgrade modal uses, so the page cannot promise a plan
 * the product does not give.
 *
 * Nobody subscribes from here: both plans start the same way, with an
 * account, and Pro is one click away once they are inside. The Pro
 * button therefore says so rather than implying a checkout.
 */
export const Pricing = ({ dashboardUrl, features, prices }: PricingProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The billing cycle being previewed. Yearly leads because it is
     * the cheaper way to pay.
     */
    const [cycle, setCycle] = useState<BillingCycle>("yearly");

    /**
     * What paying yearly saves against twelve monthly payments.
     */
    const saving = yearlySaving(prices);

    /**
     * The selected cycle's cost for one month, and the total it is
     * billed as. Both are null when Paddle could not be reached, and
     * the card then offers the plan without a figure.
     */
    const monthly = perMonthPrice(prices[cycle], cycle === "yearly" ? 12 : 1, currentLocale());
    const total = perMonthPrice(prices[cycle], 1, currentLocale());

    /**
     * Where both plans start: an account.
     */
    const start = dashboardUrl ?? login.url();

    /**
     * Zero, written in the same currency and locale as the Pro figure
     * beside it, so the two cards do not look like two currencies.
     */
    const free = new Intl.NumberFormat(currentLocale(), {
        currency: prices.yearly.currency ?? prices.monthly.currency ?? "USD",
        maximumFractionDigits: 0,
        style: "currency",
    }).format(0);

    return (
        <section id="pricing" className="bg-background relative scroll-mt-28 border-t">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-80px" }}
                    transition={{ duration: 0.6, ease: "easeOut" }}
                    className="flex max-w-3xl flex-col gap-5"
                >
                    <h2 className="font-heading text-[clamp(2rem,4vw,3.25rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                        {t("Free for one ledger. Pro when you outgrow it.")}
                    </h2>
                    <p className="text-muted-foreground max-w-[54ch] text-lg">
                        {t(
                            "Every currency, every movement and every tag is free and unlimited on both plans. Pro is for when you need more accounts, more people or your whole history.",
                        )}
                    </p>
                </m.div>

                <div className="mt-10 flex justify-center sm:mt-12">
                    <ToggleGroup
                        aria-label={t("Billing cycle")}
                        className="bg-muted rounded-full p-1"
                        onValueChange={(value) => {
                            const next = value[0] as BillingCycle | undefined;

                            if (next) {
                                setCycle(next);
                            }
                        }}
                        size="sm"
                        spacing={1}
                        value={[cycle]}
                    >
                        <ToggleGroupItem
                            className="aria-pressed:bg-background aria-pressed:text-foreground rounded-full px-4 aria-pressed:shadow-sm"
                            value="monthly"
                        >
                            {t("Monthly")}
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            className="aria-pressed:bg-background aria-pressed:text-foreground rounded-full px-4 aria-pressed:shadow-sm"
                            value="yearly"
                        >
                            {t("Yearly")}
                            {saving !== null && (
                                <span className="text-success bg-success/15 rounded-full px-1.5 py-0.5 text-xs leading-none font-semibold">
                                    {t("Save :percent%", { percent: String(saving) })}
                                </span>
                            )}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div className="mt-8 grid gap-4 lg:grid-cols-2">
                    <PlanCard
                        cta={t("Start free")}
                        description={t("A complete ledger for you and one other person.")}
                        href={start}
                        note={t("No card, now or later.")}
                        price={free}
                        priceNote={t("Free forever")}
                        title={t("Free")}
                    />
                    <BorderBeam size="pulse-outside">
                        <PlanCard
                            cta={t("Start free")}
                            description={t("No limits on accounts, people, teams or history.")}
                            featured
                            href={start}
                            note={t("You start free and move to Pro from inside the app, whenever you want.")}
                            price={monthly}
                            priceNote={
                                cycle === "yearly" && total
                                    ? t("Billed :price a year", { price: total })
                                    : t("Billed month to month")
                            }
                            title={t("Pro")}
                        />
                    </BorderBeam>
                </div>

                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: "-60px" }}
                    transition={{ duration: 0.55, ease: "easeOut" }}
                    className="mt-4"
                >
                    <PlanTable rows={features} />
                    {/* The plans are a snapshot, not a roadmap: the app keeps growing, and this says so without promising anything for later. */}
                    <p className="text-muted-foreground mt-6 text-center text-sm text-balance">
                        {t(
                            "Contamos is being built in the open. New features ship often, and this page always says what the app does today, not what it might do later.",
                        )}
                    </p>
                </m.div>
            </div>
        </section>
    );
};

interface PlanCardProps {
    cta: string;
    description: string;
    featured?: boolean;
    href: string;
    note: string;
    /** Null when Paddle could not be reached, which hides the figure. */
    price: string | null;
    priceNote: string;
    title: string;
}

/**
 * One plan: what it costs a month, what it is for, and the way in.
 */
const PlanCard = ({ cta, description, featured, href, note, price, priceNote, title }: PlanCardProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <article
            className={cn(
                "bg-card flex flex-col gap-6 rounded-2xl border p-6 sm:p-8",
                featured && "border-foreground/20 ring-foreground/10 ring-1",
            )}
        >
            <div className="flex flex-col gap-2">
                <div className="flex items-center gap-2">
                    <h3 className="font-heading text-lg font-semibold tracking-tight">{title}</h3>
                    {featured && (
                        <span className="text-muted-foreground border-border rounded-full border px-2 py-0.5 text-xs leading-none font-medium">
                            <CheckIcon aria-hidden="true" className="mr-1 inline size-3" />
                            {t("Everything, no limits")}
                        </span>
                    )}
                </div>
                <p className="text-muted-foreground text-sm leading-relaxed">{description}</p>
            </div>

            <div className="flex flex-col gap-1">
                {price ? (
                    <p className="font-heading text-4xl leading-none font-semibold tracking-tight tabular-nums">
                        {t(":price/month", { price })}
                    </p>
                ) : (
                    <p className="font-heading text-4xl leading-none font-semibold tracking-tight">{t("Pro")}</p>
                )}
                <p className="text-muted-foreground text-sm">{priceNote}</p>
            </div>

            <div className="mt-auto flex flex-col gap-3">
                <Button
                    className="w-full"
                    nativeButton={false}
                    render={<a href={href} />}
                    size="lg"
                    variant={featured ? "default" : "outline"}
                >
                    {cta} <ArrowRightIcon />
                </Button>
                <p className="text-muted-foreground text-center text-xs leading-relaxed text-balance">{note}</p>
            </div>
        </article>
    );
};
