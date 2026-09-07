import { useLaravelReactI18n } from "laravel-react-i18n";

import { cn } from "@/lib/utils";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

const LOCALES = [
    { locale: "en", label: "EN" },
    { locale: "es", label: "ES" },
] as const;

interface LanguageSwitcherProps {
    alternates: Alternates;
    className?: string;
}

/**
 * A segmented toggle of full-page links to the English/Spanish canonical
 * homepages, so `<html lang>`, SSR translations, canonical tags and the
 * locale cookie all resync in one request.
 */
export const LanguageSwitcher = ({ alternates, className }: LanguageSwitcherProps) => {
    /**
     * The current locale.
     */
    const current = useLaravelReactI18n().currentLocale();

    return (
        <div className={cn("bg-muted/70 flex items-center rounded-full p-0.5", className)} aria-label="Language">
            {LOCALES.map(({ locale, label }) => {
                const active = current === locale;

                return (
                    <a
                        key={locale}
                        href={alternates[locale]}
                        hrefLang={locale}
                        aria-current={active ? "true" : undefined}
                        className={cn(
                            "rounded-full px-2.5 py-1 text-xs font-medium transition-colors",
                            active
                                ? "bg-background text-foreground shadow-sm"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        {label}
                    </a>
                );
            })}
        </div>
    );
};
