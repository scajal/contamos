import { useLaravelReactI18n } from "laravel-react-i18n";
import { MenuIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
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

const NAV_ITEMS = [
    { href: "#product", label: "Product" },
    { href: "#plans", label: "Plans" },
    { href: "#faq", label: "FAQ" },
] as const;

/**
 * The sticky, blurred marketing header: logo, primary nav,
 * language switcher and the guest/auth call to action.
 */
export const Header = ({ alternates, dashboardUrl, appearance, onAppearanceChange }: HeaderProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The call to action, guest vs. authenticated.
     */
    const cta = dashboardUrl ? (
        <Button nativeButton={false} render={<a href={dashboardUrl} />} size="lg">
            {t("Go to dashboard")}
        </Button>
    ) : (
        <Button nativeButton={false} render={<a href={login.url()} />} size="lg">
            {t("Start now")}
        </Button>
    );

    return (
        <header className="bg-background/80 supports-backdrop-filter:bg-background/60 sticky top-0 z-40 border-b border-transparent backdrop-blur-md">
            <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <a
                    href={alternates[currentLocale() as "en" | "es"] ?? alternates.en}
                    className="flex items-center gap-2"
                >
                    <img src="/images/iso-logo.png" alt="Hormiga" width={28} height={28} className="size-7" />
                    <span className="font-heading text-lg font-semibold tracking-tight">Hormiga</span>
                </a>

                <nav aria-label={t("Primary navigation")} className="hidden items-center gap-6 md:flex">
                    {NAV_ITEMS.map((item) => (
                        <a
                            key={item.href}
                            href={item.href}
                            className="text-muted-foreground hover:text-foreground text-sm font-medium transition-colors"
                        >
                            {t(item.label)}
                        </a>
                    ))}
                </nav>

                <div className="hidden items-center gap-3 md:flex">
                    <LanguageSwitcher alternates={alternates} className="flex items-center gap-1" />
                    <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                    {cta}
                </div>

                <Sheet>
                    <SheetTrigger
                        className="md:hidden"
                        render={<Button variant="ghost" size="icon" aria-label={t("Primary navigation")} />}
                    >
                        <MenuIcon />
                    </SheetTrigger>
                    <SheetContent side="right" className="flex flex-col gap-6 p-6">
                        <SheetHeader className="sr-only">
                            <SheetTitle>Hormiga</SheetTitle>
                            <SheetDescription>{t("Primary navigation")}</SheetDescription>
                        </SheetHeader>
                        <nav aria-label={t("Primary navigation")} className="flex flex-col gap-4">
                            {NAV_ITEMS.map((item) => (
                                <SheetClose
                                    key={item.href}
                                    render={<a href={item.href} className="text-base font-medium" />}
                                >
                                    {t(item.label)}
                                </SheetClose>
                            ))}
                        </nav>
                        <div className="flex items-center gap-2">
                            <LanguageSwitcher alternates={alternates} className="flex items-center gap-2" />
                            <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                        </div>
                        {cta}
                    </SheetContent>
                </Sheet>
            </div>
        </header>
    );
};
