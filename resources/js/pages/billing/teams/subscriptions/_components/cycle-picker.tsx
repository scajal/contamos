import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { perMonthPrice, yearlySaving } from "@/lib/billing";
import subscriptions from "@/routes/billing/teams/subscriptions";
import legal from "@/routes/legal";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";

type Props = {
    prices: Record<BillingCycle, PlanPrice>;
    /** The team slug used to build the subscribe link. */
    team: string;
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
     * The locale the modal is being read in, which picks the locale
     * of the documents linked under the button.
     */
    const locale = currentLocale();

    /**
     * The billing cycle the user picked.
     */
    const [cycle, setCycle] = useState<BillingCycle>("yearly");

    /**
     * What the yearly cycle saves against twelve months of the
     * monthly one, as a whole percentage.
     */
    const saving = yearlySaving(prices);

    /**
     * The total of the selected cycle, formatted in the user's locale
     * so it reads the same way as the per-month figure above it.
     */
    const price = perMonthPrice(prices[cycle], 1, currentLocale());

    /**
     * The selected cycle's cost for one month: the yearly total
     * spread over twelve months, or the monthly total itself.
     */
    const monthly = perMonthPrice(prices[cycle], cycle === "yearly" ? 12 : 1, currentLocale());

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
                    {saving !== null && (
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
            {/* What paying commits you to, said before the checkout
             * opens rather than after. The documents open in their own
             * tab so reading one does not close the modal. */}
            <p className="text-muted-foreground text-center text-xs leading-relaxed text-balance">
                {t("Read before you pay:")}{" "}
                <a
                    className="hover:text-foreground underline underline-offset-4"
                    href={legal.terms.url({ locale })}
                    rel="noreferrer"
                    target="_blank"
                >
                    {t("Terms of service")}
                </a>
                <span aria-hidden="true" className="px-1.5">
                    ·
                </span>
                <a
                    className="hover:text-foreground underline underline-offset-4"
                    href={legal.refunds.url({ locale })}
                    rel="noreferrer"
                    target="_blank"
                >
                    {t("Refund policy")}
                </a>
            </p>
        </div>
    );
};
