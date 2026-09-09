import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Modal } from "@/components/ui/modal";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { Feature } from "@/types/app/modules/billing/enums/feature";
import { Plan } from "@/types/app/modules/billing/enums/plan";
import { PlanFeature } from "@/types/app/modules/billing/plan-feature";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";

import { CyclePicker } from "./_components/cycle-picker";
import { PlanTable } from "./_components/plan-table";
import { Splash } from "./_components/splash";

type PageProps = {
    feature: Feature | null;
    features: Array<PlanFeature>;
    plan: Plan;
    prices: Record<BillingCycle, PlanPrice>;
};

export default function Upgrade({ feature, features, plan, prices }: PageProps) {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * The team slug used to build the
     * subscribe links.
     */
    const team = user?.current_team?.slug ?? "";

    /**
     * The feature the user tried to use
     * when the limit was reached, if any.
     */
    const blocked = features.find((row) => row.feature === feature);

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                        {/** Colour wash */}
                        <Splash />

                        {/** Header */}
                        <DialogHeader className="relative gap-3 px-6 pt-14 pb-6 text-left sm:px-8">
                            <DialogTitle className="font-heading max-w-[24ch] text-2xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-3xl">
                                {t("Upgrade to Pro to unlock all features")}
                            </DialogTitle>
                            <DialogDescription className="max-w-[44ch] text-sm leading-relaxed text-pretty sm:text-base">
                                {blocked
                                    ? t("You have reached the Free plan limit for :feature.", {
                                          feature: blocked.label.toLowerCase(),
                                      })
                                    : t("The Free plan covers the basics. Pro removes every limit.")}
                            </DialogDescription>
                        </DialogHeader>

                        {/** Comparison */}
                        <div className="relative px-6 sm:px-8">
                            <PlanTable highlight={feature} rows={features} />
                        </div>

                        {/** Actions */}
                        <DialogFooter className="relative flex-col items-stretch gap-6 px-6 pt-8 pb-6 sm:flex-col sm:px-8 sm:pb-8">
                            {plan === "pro" ? (
                                <p className="text-muted-foreground text-center text-sm">
                                    {t("Your team is already on Pro.")}
                                </p>
                            ) : (
                                <CyclePicker prices={prices} team={team} />
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}
