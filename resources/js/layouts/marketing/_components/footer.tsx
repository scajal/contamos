import { Link } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { BrandMark } from "@/components/brand-mark";
import { cn } from "@/lib/utils";
import { login } from "@/routes";
import legal from "@/routes/legal";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

import { LanguageSwitcher } from "./language-switcher";
import { ThemeSwitcher, type Appearance } from "./theme-switcher";

/** The homepage's own sections, linked by anchor off the canonical URL. */
const SECTIONS = [
    { hash: "product", label: "Balances" },
    { hash: "assistants", label: "AI assistants" },
    { hash: "features", label: "Features" },
    { hash: "how-it-works", label: "How it works" },
    { hash: "pricing", label: "Pricing" },
] as const;

/** The documents that say what we do with your data and your money. */
const DOCUMENTS = [
    { label: "Privacy policy", route: legal.privacy },
    { label: "Terms of service", route: legal.terms },
    { label: "Refund policy", route: legal.refunds },
] as const;

/**
 * One labelled column of the footer: what the links under it have
 * in common, and the links themselves as a list.
 */
const FooterColumn = ({ children, title }: { children: React.ReactNode; title: string }) => (
    <div className="flex flex-col gap-4">
        <h2 className="text-sm font-medium">{title}</h2>
        <ul className="flex flex-col gap-3">{children}</ul>
    </div>
);

/**
 * A link of a footer column. The emphasized one is the way on into
 * the application, which is the only thing here a visitor is
 * being asked to do.
 */
const FooterLink = ({
    children,
    emphasized = false,
    href,
}: {
    children: React.ReactNode;
    emphasized?: boolean;
    href: string;
}) => (
    <li>
        <a
            className={cn(
                "hover:text-foreground text-sm transition-colors",
                emphasized ? "font-medium" : "text-muted-foreground",
            )}
            href={href}
        >
            {children}
        </a>
    </li>
);

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

                    {/** One column per kind of link, each under the heading
                     * that says what the column is for, so a reader scans
                     * the headings rather than a run of loose links. */}
                    <nav aria-label={t("Footer")} className="grid grid-cols-2 gap-8 sm:grid-cols-3 sm:gap-16">
                        <FooterColumn title={t("Product")}>
                            {SECTIONS.map(({ hash, label }) => (
                                <FooterLink href={isHome ? `#${hash}` : `${homeUrl}#${hash}`} key={hash}>
                                    {t(label)}
                                </FooterLink>
                            ))}
                            {/** The way on into the application closes the
                             * column, since it is the one thing here a
                             * visitor is being asked to do. */}
                            <FooterLink emphasized href={dashboardUrl ?? login.url()}>
                                {dashboardUrl ? t("Go to the dashboard") : t("Log in")}
                            </FooterLink>
                        </FooterColumn>

                        <FooterColumn title={t("Support")}>
                            <FooterLink href={isHome ? "#faq" : `${homeUrl}#faq`}>{t("Questions")}</FooterLink>
                            <FooterLink href={"mailto:hello@contamos.xyz"}>{t("Contact")}</FooterLink>
                        </FooterColumn>

                        <FooterColumn title={t("Legal")}>
                            {DOCUMENTS.map(({ label, route }) => (
                                <li key={label}>
                                    <Link
                                        className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                                        href={route.url({ locale })}
                                    >
                                        {t(label)}
                                    </Link>
                                </li>
                            ))}
                        </FooterColumn>
                    </nav>
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
