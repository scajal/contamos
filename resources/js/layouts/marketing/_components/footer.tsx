import { useLaravelReactI18n } from "laravel-react-i18n";

import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { LanguageSwitcher } from "./language-switcher";

interface FooterProps {
    alternates: Alternates;
}

/**
 * The marketing footer: logo, nav, language switcher and
 * copyright / legal placeholders.
 */
export const Footer = ({ alternates }: FooterProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <footer className="border-t">
            <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-12 sm:px-6">
                <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-2">
                        <img src="/images/iso-logo.png" alt="Hormiga" width={24} height={24} className="size-6" />
                        <span className="font-heading text-base font-semibold tracking-tight">Hormiga</span>
                    </div>

                    <nav aria-label={t("Primary navigation")} className="flex items-center gap-6">
                        <a href="#product" className="text-muted-foreground hover:text-foreground text-sm">
                            {t("Product")}
                        </a>
                        <a href="#plans" className="text-muted-foreground hover:text-foreground text-sm">
                            {t("Plans")}
                        </a>
                        <a href="#faq" className="text-muted-foreground hover:text-foreground text-sm">
                            {t("FAQ")}
                        </a>
                    </nav>

                    <LanguageSwitcher alternates={alternates} className="flex items-center gap-1" />
                </div>

                <div className="flex flex-col items-start justify-between gap-4 border-t pt-6 sm:flex-row sm:items-center">
                    <p className="text-muted-foreground text-sm">
                        {t("© :year Hormiga.", { year: String(new Date().getFullYear()) })} {t("All rights reserved.")}
                    </p>
                    <div className="flex items-center gap-4">
                        <span className="text-muted-foreground text-sm">{t("Privacy")}</span>
                        <span className="text-muted-foreground text-sm">{t("Terms")}</span>
                    </div>
                </div>
            </div>
        </footer>
    );
};
