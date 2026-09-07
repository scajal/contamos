import { useLaravelReactI18n } from "laravel-react-i18n";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";

interface FinalCtaProps {
    dashboardUrl: string | null;
}

/**
 * The final call-to-action band, on the lime primary color.
 */
export const FinalCta = ({ dashboardUrl }: FinalCtaProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="bg-primary">
            <div className="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-16 sm:px-6 sm:py-24">
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
            </div>
        </section>
    );
};
