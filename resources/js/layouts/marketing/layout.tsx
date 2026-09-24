import { usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { domAnimation, LazyMotion } from "motion/react";
import { useEffect, useState, type ReactNode } from "react";

import { cn } from "@/lib/utils";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { DocsNavigationGroup } from "@/types/app/modules/marketing/docs";

import { Footer } from "./_components/footer";
import { Header } from "./_components/header";
import type { Appearance } from "./_components/theme-switcher";

interface MarketingPageProps {
    alternates: Alternates;
    homeUrl: string;
    dashboardUrl: string | null;
    appearance: Appearance | "system";
    navigation?: Array<DocsNavigationGroup>;
    page?: string;
    [key: string]: unknown;
}

/**
 * The shell for the marketing homepage: skip link, sticky header and
 * `<main>`, footer, wrapped in a LazyMotion feature bundle so sections only
 * pay for the animation features they use. The site
 * owns its own colour scheme (dark by default), independent of the
 * app's appearance, seeded from the server so it never flashes.
 */
export const MarketingLayout = ({ children }: { children: ReactNode }) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const {
        alternates,
        homeUrl,
        dashboardUrl,
        appearance: initialAppearance,
        navigation,
        page,
    } = usePage<MarketingPageProps>().props;

    /**
     * The colour scheme, seeded from the server-read cookie. The site owns
     * its own world and stays dark until someone asks for light, so an
     * unset preference resolves to dark rather than to the device's.
     */
    const [appearance, setAppearance] = useState<Appearance>(initialAppearance === "light" ? "light" : "dark");

    // Menus and drawers render in a portal outside this shell, so the
    // document root carries the scheme too for them to follow it.
    useEffect(() => {
        const root = document.documentElement;
        const wasDark = root.classList.contains("dark");

        root.classList.toggle("dark", appearance === "dark");

        return () => {
            root.classList.toggle("dark", wasDark);
        };
    }, [appearance]);

    return (
        <LazyMotion features={domAnimation}>
            <div className={cn("bg-background text-foreground min-h-screen", appearance === "dark" && "dark")}>
                <a
                    href="#main"
                    className="bg-primary text-primary-foreground sr-only rounded-md px-4 py-2 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50"
                >
                    {t("Skip to content")}
                </a>
                <Header
                    alternates={alternates}
                    homeUrl={homeUrl}
                    dashboardUrl={dashboardUrl}
                    appearance={appearance}
                    onAppearanceChange={setAppearance}
                    docsPage={navigation ? (page ?? null) : null}
                    navigation={navigation ?? null}
                />
                <main id="main">{children}</main>
                <Footer
                    alternates={alternates}
                    homeUrl={homeUrl}
                    dashboardUrl={dashboardUrl}
                    appearance={appearance}
                    onAppearanceChange={setAppearance}
                />
            </div>
        </LazyMotion>
    );
};
