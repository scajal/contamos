import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Plans } from "@/types/app/modules/marketing/plans";

import { QUESTIONS } from "./faq";

/**
 * Localized <title>, meta description, canonical/hreflang, Open Graph,
 * Twitter card and JSON-LD (Organization, WebApplication, FAQPage) for
 * the marketing homepage.
 *
 * The structured data is built from the same sources the page renders
 * from — the FAQ list and the Paddle-previewed prices — so what search
 * engines are told can never drift from what a visitor is shown.
 */
export const SeoHead = ({ alternates, plans }: { alternates: Alternates; plans: Plans }) => {
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

    /**
     * The free plan is always offered; Pro joins it only when Paddle
     * actually returned a price, so a failed preview publishes no
     * amount rather than a wrong one.
     */
    const paidOffers =
        plans.pro.monthlyAmount === null
            ? []
            : [
                  {
                      "@type": "Offer",
                      name: "Pro",
                      price: (plans.pro.monthlyAmount / 100).toFixed(2),
                      priceCurrency: "USD",
                      url: canonical,
                  },
              ];

    const allOffers = [
        { "@type": "Offer", name: "Free", price: "0", priceCurrency: "USD", url: canonical },
        ...paidOffers,
    ];

    const offers =
        allOffers.length === 1
            ? allOffers[0]
            : {
                  "@type": "AggregateOffer",
                  offerCount: allOffers.length,
                  lowPrice: "0",
                  highPrice: allOffers[allOffers.length - 1].price,
                  priceCurrency: "USD",
                  offers: allOffers,
              };

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
                offers: offers,
            },
            {
                "@type": "FAQPage",
                mainEntity: QUESTIONS.map(({ question, answer }) => ({
                    "@type": "Question",
                    name: t(question),
                    acceptedAnswer: { "@type": "Answer", text: t(answer) },
                })),
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
