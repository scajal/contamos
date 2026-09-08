import { useLaravelReactI18n } from "laravel-react-i18n";

import { login } from "@/routes";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { LanguageSwitcher } from "./language-switcher";
import { ThemeSwitcher, type Appearance } from "./theme-switcher";

/** The homepage's own sections, linked by anchor off the canonical URL. */
const SECTIONS = [
    { hash: "product", label: "Balances" },
    { hash: "features", label: "Features" },
    { hash: "pricing", label: "Pricing" },
    { hash: "faq", label: "Questions" },
] as const;

interface FooterProps {
    alternates: Alternates;
    dashboardUrl: string | null;
    appearance: Appearance;
    onAppearanceChange: (appearance: Appearance) => void;
}

/**
 * The page's closing furniture: what the product is, a way back to any
 * section, and the language and theme controls repeated for a visitor
 * who has scrolled past the header.
 */
export const Footer = ({ alternates, dashboardUrl, appearance, onAppearanceChange }: FooterProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of the homepage in the current locale, for the
     * wordmark. The section links stay bare fragments on purpose: an
     * absolute URL to this same page reloads it, and the browser then
     * restores the old scroll position instead of honouring the anchor.
     */
    const home = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    return (
        <footer className="bg-background border-t">
            <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
                <div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between sm:gap-16">
                    <div className="flex max-w-[38ch] flex-col gap-3">
                        <a href={home} className="flex w-fit items-center gap-2">
                            <img src="/images/iso-logo.png" alt="Hormiga" width={24} height={24} className="size-6" />
                            <span className="font-heading text-base font-semibold tracking-tight">Hormiga</span>
                        </a>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {t("One shared ledger for the whole household, in every currency your money actually lives in.")}
                        </p>
                    </div>

                    <nav aria-label={t("Footer")} className="flex flex-wrap gap-x-7 gap-y-3 sm:justify-end">
                        {SECTIONS.map(({ hash, label }) => (
                            <a
                                key={hash}
                                href={`#${hash}`}
                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                            >
                                {t(label)}
                            </a>
                        ))}
                        <a
                            href={dashboardUrl ?? login.url()}
                            className="hover:text-foreground text-sm font-medium transition-colors"
                        >
                            {dashboardUrl ? t("Go to the dashboard") : t("Log in")}
                        </a>
                    </nav>
                </div>

                <div className="mt-12 flex flex-col-reverse items-start gap-4 border-t pt-8 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-sm">© {new Date().getFullYear()} Hormiga</p>

                    <div className="flex items-center gap-2">
                        <LanguageSwitcher alternates={alternates} />
                        <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                    </div>
                </div>
            </div>
        </footer>
    );
};
