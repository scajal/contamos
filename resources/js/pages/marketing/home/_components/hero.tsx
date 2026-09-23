import BorderBeam from "border-beam";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { login } from "@/routes";

import { HeroScene } from "./hero-scene";
import { LiveLedger } from "./live-ledger";

interface HeroProps {
    dashboardUrl: string | null;
}

/**
 * The entrance shared by every piece of the opening act, as CSS rather
 * than a motion component. The page is server-rendered, and a motion
 * `initial` of opacity 0 is what the server would send, leaving the
 * headline invisible until the whole bundle has downloaded, parsed and
 * hydrated — on a slow phone that is seconds of blank stage and the
 * page's largest paint pushed just as late. A CSS animation starts the
 * moment the stylesheet lands, and reduced-motion visitors get the
 * settled layout straight away.
 */
const ENTRANCE =
    "motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-6 motion-safe:fill-mode-both duration-700 ease-out";

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

    return (
        <section className="relative">
            <HeroScene />

            <div className="relative z-10 mx-auto flex w-full max-w-3xl flex-col items-center gap-8 px-4 pt-20 pb-28 text-center sm:px-6 sm:pt-28">
                <h1
                    className={cn(
                        ENTRANCE,
                        "font-heading text-[clamp(2.75rem,5.5vw,4.5rem)] leading-[1.02] font-semibold tracking-tight text-balance",
                    )}
                >
                    {t("The peace of mind of knowing exactly what you have.")}
                </h1>

                <p className={cn(ENTRANCE, "text-muted-foreground max-w-[46ch] text-lg delay-120")}>
                    {t(
                        "No more guessing, no spreadsheets, no arguing about who paid what. Every currency you share, fiat or crypto, lands in one clear picture — so you always know exactly where you stand, together.",
                    )}
                </p>

                <div className={cn(ENTRANCE, "flex flex-col items-center gap-4 delay-240")}>
                    <BorderBeam size="pulse-outside">
                        <Button
                            nativeButton={false}
                            render={<a href={dashboardUrl ?? login.url()} />}
                            size="lg"
                            variant="outline"
                        >
                            {dashboardUrl ? t("Go to dashboard") : t("Start free")} <ArrowRightIcon />
                        </Button>
                    </BorderBeam>
                    {!dashboardUrl && (
                        <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>
                    )}
                </div>

                {/*
                 * Imported statically on purpose: the ledger seeds itself
                 * deterministically, so the server can render it, and it
                 * then arrives painted in the HTML instead of as a dark
                 * placeholder that waits on a chained chunk download.
                 */}
                <div className={cn(ENTRANCE, "mt-4 w-full delay-360")}>
                    <LiveLedger />
                </div>
            </div>
        </section>
    );
};
