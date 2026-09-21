import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { majorUnits } from "@/lib/billing";
import { BillingCycle } from "@/types/app/modules/billing/enums/billing-cycle";
import { PlanPrice } from "@/types/app/modules/billing/plan-price";
import type { Alternates } from "@/types/app/modules/marketing/alternates";

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
export const SeoHead = ({
    alternates,
    prices,
}: {
    alternates: Alternates;
    prices: Record<BillingCycle, PlanPrice>;
}) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL for the page.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * The locale for the Open Graph tags.
     */
    const ogLocale = currentLocale() === "es" ? "es_ES" : "en_US";

    /**
     * The alternate locale for the Open Graph tags.
     */
    const alternateOgLocale = currentLocale() === "es" ? "en_US" : "es_ES";

    /**
     * The title for the page.
     */
    const title = t("Money, shared. Every currency, one ledger.");

    /**
     * The description for the page.
     */
    const description = t(
        "Contamos keeps your accounts, cards and currencies in one shared ledger, whoever you share money with, so you always know what you actually have. Free to start, no card required.",
    );

    /**
     * What the page offers, in the same figures the pricing section
     * renders. Pro publishes its yearly total, which is what a buyer
     * is actually charged, and it is left out entirely when Paddle
     * could not be reached rather than published as a guess.
     */
    const currency = prices.yearly.currency ?? prices.monthly.currency ?? "USD";
    const pro = majorUnits(prices.yearly);

    const allOffers = [
        { "@type": "Offer", name: "Free", price: "0", priceCurrency: currency, url: `${canonical}#pricing` },
        ...(pro
            ? [
                  {
                      "@type": "Offer",
                      name: "Pro",
                      price: pro,
                      priceCurrency: currency,
                      url: `${canonical}#pricing`,
                  },
              ]
            : []),
    ];

    /**
     * One offer publishes on its own; two or more publish as a range.
     */
    const offers =
        allOffers.length === 1
            ? allOffers[0]
            : {
                  "@type": "AggregateOffer",
                  offerCount: allOffers.length,
                  lowPrice: "0",
                  highPrice: allOffers[allOffers.length - 1].price,
                  priceCurrency: currency,
                  offers: allOffers,
              };

    /**
     * The JSON-LD for the page.
     */
    const jsonLd = {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "Organization",
                name: "Contamos",
                url: alternates.default,
                logo: `${alternates.default}/images/iso-logo.png`,
            },
            {
                "@type": "WebApplication",
                name: "Contamos",
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
