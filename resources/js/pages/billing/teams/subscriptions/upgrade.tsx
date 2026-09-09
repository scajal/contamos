import { usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Modal } from "@/components/ui/modal";
import { cn } from "@/lib/utils";
import subscriptions from "@/routes/billing/teams/subscriptions";
import { Feature } from "@/types/app/modules/billing/enums/feature";
import { Plan } from "@/types/app/modules/billing/enums/plan";
import { PlanFeature } from "@/types/app/modules/billing/plan-feature";

type PageProps = {
    feature: Feature | null;
    features: Array<PlanFeature>;
    plan: Plan;
};

export default function Upgrade({ feature, features, plan }: PageProps) {
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

    /**
     * Format a plan limit for display.
     */
    const limit = (value: number | null) => (value === null ? t("Unlimited") : String(value));

    return (
        <Modal>
            {({ rootProps }) => (
                <Dialog {...rootProps}>
                    <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                        {/** Colour wash */}
                        <Splash />

                        {/** Header */}
                        <DialogHeader className="relative gap-3 px-8 pt-14 pb-6 text-left">
                            <DialogTitle className="font-heading max-w-[24ch] text-3xl leading-[1.05] font-semibold tracking-tight text-balance">
                                {t("Upgrade to Pro to unlock all features")}
                            </DialogTitle>
                            <DialogDescription className="max-w-[44ch] text-base leading-relaxed text-pretty">
                                {blocked
                                    ? t("You have reached the Free plan limit for :feature.", {
                                          feature: blocked.label.toLowerCase(),
                                      })
                                    : t("The Free plan covers the basics. Pro removes every limit.")}
                            </DialogDescription>
                        </DialogHeader>

                        {/** Comparison */}
                        <div className="relative px-8">
                            <div className="overflow-hidden rounded-xl border">
                                <table className="w-full border-collapse text-sm">
                                    <thead>
                                        <tr className="bg-muted/50 text-muted-foreground text-xs">
                                            <th className="px-4 py-2 text-left font-normal" scope="col">
                                                <span className="sr-only">{t("Feature")}</span>
                                            </th>
                                            <th className="w-20 px-4 py-2 text-right font-normal" scope="col">
                                                {t("Free")}
                                            </th>
                                            <th
                                                className="text-foreground w-24 px-4 py-2 text-right font-medium"
                                                scope="col"
                                            >
                                                {t("Pro")}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {features.map((row) => {
                                            const isBlocked = row.feature === feature;

                                            return (
                                                <tr
                                                    key={row.feature}
                                                    className={cn(
                                                        "border-t",
                                                        isBlocked && "bg-foreground/[0.04] font-medium",
                                                    )}
                                                >
                                                    <td className="px-4 py-3 text-left">{row.label}</td>
                                                    <td className="text-muted-foreground px-4 py-3 text-right tabular-nums">
                                                        {limit(row.free)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {limit(row.pro)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/** Actions */}
                        <DialogFooter className="relative flex-col gap-2 px-8 pt-8 pb-8 sm:flex-col">
                            {plan === "pro" ? (
                                <p className="text-muted-foreground text-center text-sm">
                                    {t("Your team is already on Pro.")}
                                </p>
                            ) : (
                                <>
                                    <Button
                                        className="w-full"
                                        nativeButton={false}
                                        render={
                                            <ModalLink href={subscriptions.subscribe.url({ team, cycle: "yearly" })}>
                                                {t("Upgrade yearly")}
                                            </ModalLink>
                                        }
                                        size="lg"
                                    />
                                    <Button
                                        className="w-full"
                                        nativeButton={false}
                                        render={
                                            <ModalLink href={subscriptions.subscribe.url({ team, cycle: "monthly" })}>
                                                {t("Upgrade monthly")}
                                            </ModalLink>
                                        }
                                        size="lg"
                                        variant="ghost"
                                    />
                                </>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </Modal>
    );
}

/**
 * The dialog's one piece of free colour: the same three hues as the
 * pricing ribbon on the homepage, blurred into a wash that sits behind
 * the title and fades out before the comparison so the numbers stay on
 * plain ground.
 */
const Splash = () => {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-x-0 top-0 h-64 overflow-hidden [mask-image:linear-gradient(to_bottom,black_30%,transparent)]"
        >
            <div className="absolute -top-24 -left-16 size-72 rounded-full bg-[oklch(0.72_0.19_25)] opacity-45 blur-[80px] dark:opacity-30" />
            <div className="absolute -top-28 left-1/3 size-80 rounded-full bg-[oklch(0.74_0.16_265)] opacity-45 blur-[90px] dark:opacity-30" />
            <div className="absolute -top-20 -right-16 size-72 rounded-full bg-[oklch(0.78_0.15_190)] opacity-45 blur-[80px] dark:opacity-30" />
        </div>
    );
};
