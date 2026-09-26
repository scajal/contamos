import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";

import { BrandMark } from "@/components/brand-mark";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { login } from "@/routes";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { DocsNavigationGroup } from "@/types/app/modules/marketing/docs";

import { HeaderMenu } from "./header-menu";
import { LanguageSwitcher } from "./language-switcher";
import { ThemeSwitcher, type Appearance } from "./theme-switcher";

interface HeaderProps {
    alternates: Alternates;
    homeUrl: string;
    dashboardUrl: string | null;
    appearance: Appearance;
    onAppearanceChange: (appearance: Appearance) => void;
    docsPage: string | null;
    navigation: Array<DocsNavigationGroup> | null;
}

/**
 * The marketing header, built as a floating pill rather than a bar: it
 * detaches from the top edge, keeps its own border, blur and shadow,
 * and so reads as an object resting on the stage — the same language as
 * the 3D props and the balances panel. It is kept nearly opaque on
 * purpose: at lower opacities the dark ledger card scrolling behind it
 * turns the pill grey and muddies the wordmark. The outer band stays
 * click-through so only the pill itself catches the pointer.
 */
export const Header = ({
    alternates,
    homeUrl,
    dashboardUrl,
    appearance,
    onAppearanceChange,
    docsPage,
    navigation,
}: HeaderProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * Whether the reader is on the homepage, where the section
     * links stay bare fragments.
     */
    const isHome = (alternates[currentLocale() as "en" | "es"] ?? alternates.en) === homeUrl;

    return (
        <header className="pointer-events-none sticky top-0 z-40 mx-auto max-w-6xl px-4 pt-4 sm:px-6">
            <div className="bg-background/95 supports-backdrop-filter:bg-background/85 pointer-events-auto flex h-14 items-center justify-between gap-3 rounded-full border py-2 pr-2 pl-4 shadow-lg backdrop-blur-xl sm:pl-5">
                <div className="flex items-center gap-4">
                    <a href={homeUrl} className="flex shrink-0 items-center gap-2">
                        <BrandMark />
                        <span className="font-heading text-base font-semibold tracking-tight">
                            {import.meta.env.VITE_APP_NAME}
                        </span>
                    </a>
                    <HeaderMenu
                        docsPage={docsPage}
                        homeUrl={homeUrl}
                        isHome={isHome}
                        navigation={navigation}
                        variant="inline"
                    />
                </div>

                <div className="flex items-center gap-1.5 sm:gap-2">
                    <LanguageSwitcher alternates={alternates} />
                    <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                    <Separator orientation="vertical" />
                    {/** Below the drawer breakpoint the call to action moves
                     * into the drawer, where the pill has no room for it */}
                    <Button
                        className={cn("rounded-full", docsPage ? "max-lg:hidden" : "max-md:hidden")}
                        nativeButton={false}
                        render={<a href={dashboardUrl ?? login.url()} />}
                        size="sm"
                    >
                        {dashboardUrl ? t("Go to dashboard") : t("Log in")}
                        <ArrowRightIcon />
                    </Button>
                    <HeaderMenu
                        dashboardUrl={dashboardUrl}
                        docsPage={docsPage}
                        homeUrl={homeUrl}
                        isHome={isHome}
                        navigation={navigation}
                        variant="drawer"
                    />
                </div>
            </div>
        </header>
    );
};
