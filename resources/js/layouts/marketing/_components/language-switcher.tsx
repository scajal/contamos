import { useLaravelReactI18n } from "laravel-react-i18n";

import type { Alternates } from "@/types/app/modules/marketing/alternates";

interface LanguageSwitcherProps {
    alternates: Alternates;
    className?: string;
}

/**
 * Full-page links to the English/Spanish canonical homepages so <html lang>,
 * SSR translations, canonical tags and the locale cookie all resync in one request.
 */
export const LanguageSwitcher = ({ alternates, className }: LanguageSwitcherProps) => {
    /**
     * The current locale.
     */
    const locale = useLaravelReactI18n().currentLocale();

    return (
        <div className={className} aria-label="Language">
            <a
                href={alternates.en}
                hrefLang="en"
                aria-current={locale === "en" ? "true" : undefined}
                className="text-muted-foreground hover:text-foreground aria-current:text-foreground rounded-md px-1.5 py-1 text-sm font-medium transition-colors"
            >
                EN
            </a>
            <span aria-hidden="true" className="text-muted-foreground">
                /
            </span>
            <a
                href={alternates.es}
                hrefLang="es"
                aria-current={locale === "es" ? "true" : undefined}
                className="text-muted-foreground hover:text-foreground aria-current:text-foreground rounded-md px-1.5 py-1 text-sm font-medium transition-colors"
            >
                ES
            </a>
        </div>
    );
};
