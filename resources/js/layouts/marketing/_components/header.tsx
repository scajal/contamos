import { useLaravelReactI18n } from "laravel-react-i18n";

import { Button } from "@/components/ui/button";
import { login } from "@/routes";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { LanguageSwitcher } from "./language-switcher";
import { ThemeSwitcher, type Appearance } from "./theme-switcher";

interface HeaderProps {
    alternates: Alternates;
    dashboardUrl: string | null;
    appearance: Appearance;
    onAppearanceChange: (appearance: Appearance) => void;
}

/**
 * The sticky, blurred marketing header: logo, language and theme
 * switchers, and the guest/auth call to action.
 */
export const Header = ({ alternates, dashboardUrl, appearance, onAppearanceChange }: HeaderProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    return (
        <header className="bg-background/80 supports-backdrop-filter:bg-background/60 sticky top-0 z-40 border-b border-transparent backdrop-blur-md">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-4 sm:gap-4 sm:px-6">
                <a
                    href={alternates[currentLocale() as "en" | "es"] ?? alternates.en}
                    className="flex items-center gap-2"
                >
                    <img src="/images/iso-logo.png" alt="Hormiga" width={28} height={28} className="size-7" />
                    <span className="font-heading text-lg font-semibold tracking-tight">Hormiga</span>
                </a>

                <div className="flex items-center gap-1 sm:gap-3">
                    <LanguageSwitcher alternates={alternates} className="flex items-center gap-1" />
                    <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                    <Button
                        nativeButton={false}
                        render={<a href={dashboardUrl ?? login.url()} />}
                        size="lg"
                        className="max-sm:h-8 max-sm:px-2.5 max-sm:text-xs"
                    >
                        {t(dashboardUrl ? "Go to dashboard" : "Start now")}
                    </Button>
                </div>
            </div>
        </header>
    );
};
