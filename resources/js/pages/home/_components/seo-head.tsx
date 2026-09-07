import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import type { Alternates } from "@/types/app/modules/marketing/alternates";

/**
 * Localized <title>, meta description, canonical/hreflang, Open Graph,
 * Twitter card and JSON-LD (Organization, WebApplication) for the
 * marketing homepage.
 */
export const SeoHead = ({ alternates }: { alternates: Alternates }) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    const locale = currentLocale();
    const canonical = alternates[locale as "en" | "es"] ?? alternates.en;
    const ogLocale = locale === "es" ? "es_ES" : "en_US";
    const alternateOgLocale = locale === "es" ? "en_US" : "es_ES";

    const title = t("Money, shared. Every currency, one ledger.");
    const description = t(
        "Hormiga keeps your household's accounts, cards and currencies in one shared ledger, so you always know what you actually have. Free to start, no card required.",
    );

    const jsonLd = {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                name: "Hormiga",
                url: alternates.default,
                logo: `${alternates.default}/images/iso-logo.png`,
            },
            {
                "@type": "WebApplication",
                name: "Hormiga",
                applicationCategory: "FinanceApplication",
                operatingSystem: "Web",
                url: canonical,
                offers: {
                    "@type": "Offer",
                    price: "0",
                    priceCurrency: "USD",
                },
            },
        ],
    };

    return (
        <Head title={title}>
            <meta name="description" content={description} />

            <link rel="canonical" href={canonical} />
            <link rel="alternate" hrefLang="en" href={alternates.en} />
            <link rel="alternate" hrefLang="es" href={alternates.es} />
            <link rel="alternate" hrefLang="x-default" href={alternates.default} />

            <meta property="og:type" content="website" />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={canonical} />
            <meta property="og:locale" content={ogLocale} />
            <meta property="og:locale:alternate" content={alternateOgLocale} />
            <meta property="og:image" content={`${alternates.default}/images/og.png`} />

            <meta name="twitter:card" content="summary_large_image" />

            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
        </Head>
    );
};
