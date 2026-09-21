import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import subscriptions from "@/routes/billing/teams/subscriptions";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";

type Props = {
    prices: Record<BillingCycle, PlanPrice>;
    /** The team slug used to build the subscribe link. */
    team: string;
};

/**
 * Format a Paddle total in minor units as the equivalent price of one
 * month, in the user's locale. The currency decides how many minor
 * units make one major unit, so a two-decimal currency divides by a
 * hundred and a zero-decimal one by nothing. Null when the amount or
 * the currency is missing, which also covers a price cached before
 * the currency was part of it.
 */
const perMonth = (price: PlanPrice, months: number, locale: string): string | null => {
    if (!price.amount || !price.currency) {
        return null;
    }

    const formatter = new Intl.NumberFormat(locale, { style: "currency", currency: price.currency });

    const decimals = formatter.resolvedOptions().maximumFractionDigits ?? 2;

    return formatter.format(price.amount / 10 ** decimals / months);
};

/**
 * The monthly or yearly choice and the single upgrade button that
 * carries the selected cycle's price. Yearly is preselected because
 * it is the better deal: the pill says by how much, and the price
 * line shows the yearly plan as what it costs per month, with the
 * yearly total underneath, so the cheapest way to pay reads as
 * the cheapest.
 */
export const CyclePicker = ({ prices, team }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The billing cycle the user picked.
     */
    const [cycle, setCycle] = useState<BillingCycle>("yearly");

    /**
     * What the yearly cycle saves against twelve months of the
     * monthly one, as a whole percentage. Null when either price
     * is unknown.
     */
    const saving =
        prices.monthly.amount && prices.yearly.amount
            ? Math.round((1 - prices.yearly.amount / (prices.monthly.amount * 12)) * 100)
            : null;

    /**
     * The formatted price of the selected cycle, if Paddle
     * could be reached.
     */
    const price = prices[cycle].formatted;

    /**
     * The selected cycle's cost for one month: the yearly total
     * spread over twelve months, or the monthly total itself.
     */
    const monthly = perMonth(prices[cycle], cycle === "yearly" ? 12 : 1, currentLocale());

    return (
        <div className="flex flex-col items-center gap-6">
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
                    className="aria-pressed:bg-background aria-pressed:text-foreground rounded-full px-3 aria-pressed:shadow-sm"
                    value="monthly"
                >
                    {t("Monthly")}
                </ToggleGroupItem>
                <ToggleGroupItem
                    className="aria-pressed:bg-background aria-pressed:text-foreground rounded-full px-3 aria-pressed:shadow-sm"
                    value="yearly"
                >
                    {t("Yearly")}
                    {saving !== null && saving > 0 && (
                        <span className="rounded-full bg-emerald-500/15 px-1.5 py-0.5 text-[11px] leading-none font-semibold text-emerald-700 dark:text-emerald-400">
                            {t("Save :percent%", { percent: String(saving) })}
                        </span>
                    )}
                </ToggleGroupItem>
            </ToggleGroup>
            {monthly && price && (
                <div className="flex flex-col items-center gap-1 text-center">
                    <p className="font-heading text-3xl font-semibold tracking-tight">
                        {t(":price/month", { price: monthly })}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {cycle === "yearly" ? t("Billed :price a year", { price }) : t("Billed month to month")}
                    </p>
                </div>
            )}
            <Button
                className="w-full"
                nativeButton={false}
                render={
                    <ModalLink href={subscriptions.subscribe.url({ team, cycle })}>
                        {price ? t("Upgrade for :price", { price }) : t("Upgrade")}
                    </ModalLink>
                }
                size="lg"
            />
        </div>
    );
};
