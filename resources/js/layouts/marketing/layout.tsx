import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { domAnimation, LazyMotion } from "motion/react";
import type { ReactNode } from "react";

import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { Footer } from "./_components/footer";
import { Header } from "./_components/header";

interface MarketingPageProps {
    alternates: Alternates;
    dashboardUrl: string | null;
    [key: string]: unknown;
}

/**
 * The shell for the marketing homepage: skip link, sticky header,
 * `<main>` and footer, wrapped in a LazyMotion feature bundle so
 * sections only pay for the animation features they use.
 */
export const MarketingLayout = ({ children }: { children: ReactNode }) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { alternates, dashboardUrl } = usePage<MarketingPageProps>().props;

    return (
        <LazyMotion features={domAnimation}>
            <a
                href="#main"
                className="bg-primary text-primary-foreground sr-only rounded-md px-4 py-2 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50"
            >
                {t("Skip to content")}
            </a>
            <Header alternates={alternates} dashboardUrl={dashboardUrl} />
            <main id="main">{children}</main>
            <Footer alternates={alternates} />
        </LazyMotion>
    );
};
