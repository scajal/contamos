import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";

import { BrandMark } from "@/components/brand-mark";
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
 * The marketing header, built as a floating pill rather than a bar: it
 * detaches from the top edge, keeps its own border, blur and shadow,
 * and so reads as an object resting on the stage — the same language as
 * the 3D props and the balances panel. It is kept nearly opaque on
 * purpose: at lower opacities the dark ledger card scrolling behind it
 * turns the pill grey and muddies the wordmark. The outer band stays
 * click-through so only the pill itself catches the pointer.
 */
export const Header = ({ alternates, dashboardUrl, appearance, onAppearanceChange }: HeaderProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    return (
        <header className="pointer-events-none sticky top-0 z-40 px-4 pt-4 sm:px-6">
            <div className="bg-background/95 supports-backdrop-filter:bg-background/85 pointer-events-auto mx-auto flex h-14 max-w-3xl items-center justify-between gap-3 rounded-full border py-2 pr-2 pl-4 shadow-lg backdrop-blur-xl sm:pl-5">
                <a
                    href={alternates[currentLocale() as "en" | "es"] ?? alternates.en}
                    className="flex shrink-0 items-center gap-2"
                >
                    <BrandMark />
                    <span className="font-heading text-base font-semibold tracking-tight">contamos</span>
                </a>

                <div className="flex items-center gap-1.5 sm:gap-2">
                    <LanguageSwitcher alternates={alternates} />
                    <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />

                    <span aria-hidden="true" className="bg-border mx-0.5 h-5 w-px max-sm:hidden" />

                    <Button
                        className="rounded-full max-sm:px-3 max-sm:text-xs"
                        nativeButton={false}
                        render={<a href={dashboardUrl ?? login.url()} />}
                        size="sm"
                    >
                        {t("Log in")}
                        <ArrowRightIcon />
                    </Button>
                </div>
            </div>
        </header>
    );
};
