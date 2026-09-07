import { BorderBeam } from "border-beam";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";
import { lazy, Suspense } from "react";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";

const LiveLedger = lazy(() => import("./live-ledger").then((module) => ({ default: module.LiveLedger })));

interface HeroProps {
    dashboardUrl: string | null;
}

/**
 * The hero: centered H1 and lead, the CTAs, then the "Live ledger"
 * kinetic moment below them — lazy-loaded behind a same-size
 * placeholder to avoid layout shift.
 */
export const Hero = ({ dashboardUrl }: HeroProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    return (
        <section className="relative mx-auto flex max-w-4xl flex-col items-center gap-10 overflow-hidden px-4 pt-16 pb-20 text-center sm:px-6 sm:pt-24 sm:pb-28 lg:pt-32">
            <div
                aria-hidden="true"
                className="bg-primary/15 pointer-events-none absolute -top-32 left-1/2 size-[36rem] -translate-x-1/2 rounded-full blur-3xl"
            />

            <m.div
                initial={reducedMotion ? false : { opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, ease: "easeOut" }}
                className="relative flex flex-col items-center gap-6"
            >
                <h1 className="font-heading text-[clamp(2.5rem,4.5vw,3.75rem)] leading-[1.05] font-semibold tracking-tight text-balance">
                    {t("The peace of mind of knowing exactly what you have.")}
                </h1>
                <p className="text-muted-foreground max-w-[50ch] text-lg">
                    {t(
                        "No more guessing, no spreadsheets, no arguing about who paid what. Every euro, dollar and bitcoin your household touches lands in one clear picture — so you always know exactly where you stand, together.",
                    )}
                </p>
            </m.div>

            <m.div
                initial={reducedMotion ? false : { opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, ease: "easeOut", delay: 0.15 }}
                className="relative flex flex-col items-center gap-4"
            >
                <div className="flex flex-col items-center gap-3 sm:flex-row">
                    <BorderBeam>
                        <Button
                            nativeButton={false}
                            render={<a href={dashboardUrl ?? login.url()} />}
                            size="xl"
                            variant="outline"
                        >
                            {t(dashboardUrl ? "Go to dashboard" : "Start now")} <ArrowRightIcon />
                        </Button>
                    </BorderBeam>
                    <Button nativeButton={false} render={<a href="#product" />} variant="ghost" size="xl">
                        {t("See how it works")}
                    </Button>
                </div>
                {!dashboardUrl && <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>}
            </m.div>

            <m.div
                initial={reducedMotion ? false : { opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, ease: "easeOut", delay: 0.3 }}
                className="relative w-full max-w-lg"
            >
                <Suspense fallback={<div className="min-h-[340px] rounded-2xl bg-zinc-950/95" aria-hidden="true" />}>
                    <LiveLedger />
                </Suspense>
            </m.div>
        </section>
    );
};
