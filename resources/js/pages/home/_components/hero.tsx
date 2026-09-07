import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";
import { m, useReducedMotion } from "motion/react";
import { lazy, Suspense } from "react";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";

import { HeroScene } from "./hero-scene";

const LiveLedger = lazy(() => import("./live-ledger").then((module) => ({ default: module.LiveLedger })));

interface HeroProps {
    dashboardUrl: string | null;
}

/**
 * The hero: one stage holding the whole opening act — headline, CTAs
 * and the live ledger — with the product's 3D props arranged around
 * them in depth on a floor that runs to a vanishing point. Keeping the
 * ledger inside the section is what stops the floor and the props from
 * being clipped mid-scene.
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
        <section className="bg-background relative overflow-hidden">
            <HeroScene />

            <div className="relative z-10 mx-auto flex w-full max-w-3xl flex-col items-center gap-8 px-4 pt-20 pb-28 text-center sm:px-6 sm:pt-28">
                <m.h1
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7, ease: "easeOut" }}
                    className="font-heading text-[clamp(2.75rem,5.5vw,4.5rem)] leading-[1.02] font-semibold tracking-tight text-balance"
                >
                    {t("The peace of mind of knowing exactly what you have.")}
                </m.h1>

                <m.p
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7, ease: "easeOut", delay: 0.12 }}
                    className="text-muted-foreground max-w-[46ch] text-lg"
                >
                    {t(
                        "No more guessing, no spreadsheets, no arguing about who paid what. Every euro, dollar and bitcoin your household touches lands in one clear picture — so you always know exactly where you stand, together.",
                    )}
                </m.p>

                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7, ease: "easeOut", delay: 0.24 }}
                    className="flex flex-col items-center gap-4"
                >
                    <Button nativeButton={false} render={<a href={dashboardUrl ?? login.url()} />} size="lg">
                        {t("Start")} <ArrowRightIcon />
                    </Button>
                    {!dashboardUrl && (
                        <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>
                    )}
                </m.div>

                <m.div
                    initial={reducedMotion ? false : { opacity: 0, y: 24 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.7, ease: "easeOut", delay: 0.36 }}
                    className="mt-4 w-full"
                >
                    <Suspense
                        fallback={<div className="min-h-[340px] rounded-2xl bg-zinc-950/95" aria-hidden="true" />}
                    >
                        <LiveLedger />
                    </Suspense>
                </m.div>
            </div>
        </section>
    );
};
