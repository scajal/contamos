import { Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { BrandMark } from "@/components/brand-mark";
import { login } from "@/routes";
import legal from "@/routes/legal";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { LanguageSwitcher } from "./language-switcher";
import { ThemeSwitcher, type Appearance } from "./theme-switcher";

/** The homepage's own sections, linked by anchor off the canonical URL. */
const SECTIONS = [
    { hash: "product", label: "Balances" },
    { hash: "features", label: "Features" },
    { hash: "how-it-works", label: "How it works" },
    { hash: "pricing", label: "Pricing" },
    { hash: "faq", label: "Questions" },
] as const;

/** The documents that say what we do with your data and your money. */
const DOCUMENTS = [
    { label: "Privacy policy", route: legal.privacy },
    { label: "Terms of service", route: legal.terms },
    { label: "Refund policy", route: legal.refunds },
] as const;

interface FooterProps {
    alternates: Alternates;
    homeUrl: string;
    dashboardUrl: string | null;
    appearance: Appearance;
    onAppearanceChange: (appearance: Appearance) => void;
}

/**
 * The page's closing furniture: what the product is, a way back to any
 * section, the legal documents, a way to reach a person, and the
 * language and theme controls repeated for a visitor who has scrolled
 * past the header.
 */
export const Footer = ({ alternates, homeUrl, dashboardUrl, appearance, onAppearanceChange }: FooterProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The address a visitor writes to, shared by the server so the
     * footer, the application and the billing states all offer the
     * same one.
     */
    const { support } = usePage().props;

    /**
     * The locale this page is being read in, which also picks the
     * locale of the legal documents linked below.
     */
    const locale = currentLocale() as "en" | "es";

    /**
     * The canonical URL of the page being read.
     */
    const canonical = alternates[locale] ?? alternates.en;

    /**
     * Whether the reader is on the homepage. The section links stay
     * bare fragments there on purpose: an absolute URL to the same
     * page reloads it, and the browser then restores the old scroll
     * position instead of honouring the anchor. Anywhere else there
     * is no section to jump to, so they lead home first.
     */
    const isHome = canonical === homeUrl;

    return (
        <footer className="bg-background border-t">
            <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
                <div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between sm:gap-16">
                    <div className="flex max-w-[38ch] flex-col gap-3">
                        <a href={homeUrl} className="flex w-fit items-center gap-2">
                            <BrandMark />
                            <span className="font-heading text-base font-semibold tracking-tight">contamos</span>
                        </a>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {t(
                                "One shared ledger for everyone you share money with, in every currency it actually lives in.",
                            )}
                        </p>
                    </div>

                    <div className="flex flex-col gap-8 sm:flex-row sm:gap-16">
                        <nav aria-label={t("Footer")} className="flex flex-wrap gap-x-7 gap-y-3">
                            {SECTIONS.map(({ hash, label }) => (
                                <a
                                    key={hash}
                                    href={isHome ? `#${hash}` : `${homeUrl}#${hash}`}
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

                        <nav aria-label={t("Legal")} className="flex flex-wrap gap-x-7 gap-y-3">
                            {DOCUMENTS.map(({ label, route }) => (
                                <Link
                                    key={label}
                                    href={route.url({ locale })}
                                    className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                >
                                    {t(label)}
                                </Link>
                            ))}
                            <a
                                href={`mailto:${support.email}`}
                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                            >
                                {t("Support")}
                            </a>
                        </nav>
                    </div>
                </div>

                <div className="mt-12 flex flex-col-reverse items-start gap-4 border-t pt-8 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-sm">© {new Date().getFullYear()} Contamos</p>

                    <div className="flex items-center gap-2">
                        <LanguageSwitcher alternates={alternates} />
                        <ThemeSwitcher appearance={appearance} onChange={onAppearanceChange} />
                    </div>
                </div>
            </div>
        </footer>
    );
};
