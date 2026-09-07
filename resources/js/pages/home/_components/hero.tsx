import { BorderBeam } from "border-beam";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { lazy, Suspense } from "react";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";

const LiveLedger = lazy(() => import("./live-ledger").then((module) => ({ default: module.LiveLedger })));

interface HeroProps {
    dashboardUrl: string | null;
}

/**
 * The hero: H1, lead, CTAs and the "Live ledger" kinetic moment,
 * lazy-loaded behind a same-size placeholder to avoid layout shift.
 */
export const Hero = ({ dashboardUrl }: HeroProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="mx-auto grid max-w-6xl gap-12 px-4 pt-16 pb-20 sm:px-6 sm:pt-24 sm:pb-28 lg:grid-cols-2 lg:items-center lg:pt-32">
            <div className="flex flex-col items-start gap-7 text-left">
                <h1 className="font-heading text-[clamp(2.5rem,4.5vw,3.75rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                    {t("Money you share, in every currency you actually use.")}
                </h1>
                <p className="text-muted-foreground max-w-[50ch] text-lg">
                    {t(
                        "Hormiga keeps your household's accounts, cards and cash in one ledger. Log an expense in euros, a salary in dollars or a bit of bitcoin, and see what you really have, per currency, without spreadsheets.",
                    )}
                </p>
                <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    <BorderBeam size="pulse-outside">
                        <Button nativeButton={false} render={<a href={dashboardUrl ?? login.url()} />} size="xl">
                            {t(dashboardUrl ? "Go to dashboard" : "Start now")}
                        </Button>
                    </BorderBeam>
                    <Button nativeButton={false} render={<a href="#product" />} variant="ghost" size="xl">
                        {t("See how it works")}
                    </Button>
                </div>
                {!dashboardUrl && <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>}
            </div>

            <Suspense fallback={<div className="min-h-[340px] rounded-2xl bg-zinc-950/95" aria-hidden="true" />}>
                <LiveLedger />
            </Suspense>
        </section>
    );
};
