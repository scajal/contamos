import { useLaravelReactI18n } from "laravel-react-i18n";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";

import { Reveal } from "./reveal";

interface FinalCtaProps {
    dashboardUrl: string | null;
}

/**
 * The final call-to-action band, on the lime primary color, with a
 * large rotated ghost shape for depth and a fade-up entrance.
 */
export const FinalCta = ({ dashboardUrl }: FinalCtaProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="bg-primary relative overflow-hidden">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute -top-16 -right-16 size-72 rotate-12 rounded-[3rem] border-8 border-zinc-950/10 sm:size-96"
            />

            <Reveal className="relative mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-16 sm:px-6 sm:py-24">
                <h2 className="font-heading text-3xl font-semibold tracking-tight text-zinc-950 md:text-5xl">
                    {t("Know what you have, together.")}
                </h2>
                <Button
                    nativeButton={false}
                    render={<a href={dashboardUrl ?? login.url()} />}
                    size="xl"
                    className="bg-zinc-950 text-zinc-50 hover:bg-zinc-950/80"
                >
                    {t(dashboardUrl ? "Go to dashboard" : "Start now")}
                </Button>
            </Reveal>
        </section>
    );
};
