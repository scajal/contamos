import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import type { Alternates } from "@/types/app/modules/marketing/alternates";

const FAQ = [
    "Is Hormiga free?",
    "Yes. The Free plan covers a household getting started, with one account, one member and 100 transactions a month, no card required.",
    "Which currencies can I use?",
    "Any fiat currency or bitcoin you add to your team. Hormiga tracks balances per currency and keeps FX and crypto quotes up to date automatically.",
    "Can I share it with my partner or family?",
    "Yes. Invite them to your team and everyone logs transactions into the same shared ledger.",
    "Does it connect to my bank?",
    "No. Hormiga is manual by design, so every entry is a deliberate choice, not an imported guess.",
    "Can I switch languages?",
    "Yes. Switch between English and Spanish at any time from the language switcher.",
    "What happens when I hit the free limits?",
    "You can keep using what you've already added. Upgrade to Pro whenever your household needs more accounts, members or transactions.",
] as const;

/**
 * Localized <title>, meta description, canonical/hreflang, Open Graph,
 * Twitter card and JSON-LD (Organization, WebApplication, FAQPage) for
 * the marketing homepage.
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
            {
                "@type": "FAQPage",
                mainEntity: FAQ.reduce<Array<Record<string, unknown>>>((questions, value, index) => {
                    if (index % 2 !== 0) {
                        return questions;
                    }

                    questions.push({
                        "@type": "Question",
                        name: t(value),
                        acceptedAnswer: {
                            "@type": "Answer",
                            text: t(FAQ[index + 1] ?? ""),
                        },
                    });

                    return questions;
                }, []),
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
