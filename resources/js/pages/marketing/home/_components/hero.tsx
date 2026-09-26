import BorderBeam from "border-beam";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon, SparklesIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { login } from "@/routes";
import type { Launch } from "@/types/app/modules/marketing/objects/launch";

import { HeroScene } from "./hero-scene";
import { LiveLedger } from "./live-ledger";

interface HeroProps {
    dashboardUrl: string | null;
    launch: Launch | null;
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
export const Hero = ({ dashboardUrl, launch }: HeroProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="relative">
            <HeroScene />

            <div className="relative z-10 mx-auto flex w-full max-w-3xl flex-col items-center gap-8 px-4 pt-20 pb-28 text-center sm:px-6 sm:pt-28">
                {launch ? (
                    /** While the Product Hunt launch runs, it takes the announcement's place. */
                    <a
                        href={launch.phase === "offer" ? "#pricing" : launch.url}
                        {...(launch.phase === "offer" ? {} : { target: "_blank", rel: "noopener" })}
                        className={cn(
                            ENTRANCE,
                            "bg-background/80 hover:border-foreground/30 -mb-2 flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm shadow-sm backdrop-blur transition-colors",
                        )}
                    >
                        <span className="flex shrink-0 items-center gap-1 rounded-full bg-[#ff6154] px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-white">
                            {launch.phase === "offer" ? t("Launch offer") : "Product Hunt"}
                        </span>
                        <span>
                            {launch.phase === "upcoming" && t("Launching October 24. Get notified")}
                            {launch.phase === "live" && t("We're live today. Come say hi")}
                            {launch.phase === "offer" && t("Use code :code at checkout", { code: launch.code ?? "" })}
                        </span>
                        <ArrowRightIcon aria-hidden="true" className="text-muted-foreground size-3.5" />
                    </a>
                ) : (
                    /** The assistants lead the pitch alongside the ledger, one tap from their act. */
                    <a
                        href="#assistants"
                        className={cn(
                            ENTRANCE,
                            "bg-background/80 hover:border-foreground/30 -mb-2 flex items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm shadow-sm backdrop-blur transition-colors",
                        )}
                    >
                        <span className="bg-primary text-primary-foreground flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold">
                            <SparklesIcon aria-hidden="true" className="size-3" />
                            {t("New")}
                        </span>
                        <span>{t("Talk to your ledger from Claude or ChatGPT")}</span>
                        <ArrowRightIcon aria-hidden="true" className="text-muted-foreground size-3.5" />
                    </a>
                )}

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
                        "No more guessing, no spreadsheets, no arguing about who paid what. Every currency you share, fiat or crypto, lands in one clear picture — and your AI assistant can read it, answer from it and keep it up to date for you.",
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
