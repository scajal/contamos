import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { formatDate } from "@/lib/utils";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { LegalSection } from "@/types/app/modules/marketing/legal";

interface Props {
    alternates: Alternates;
    description: string;
    sections: Array<LegalSection>;
    title: string;
    updatedAt: string;
}

/**
 * The address the documents tell a reader to write to.
 */
const SUPPORT_EMAIL = "hello@contamos.xyz";

/**
 * The shell the three legal documents share: their head tags, the
 * title with the date it last changed, and the numbered sections
 * themselves.
 *
 * Every string reaching it is a translation key. The documents name the
 * company and the law they are written under in their own copy; only
 * the support address and the date each document carries are filled
 * in here.
 */
export const LegalDocument = ({ alternates, description, sections, title, updatedAt }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of this document in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * What every document needs to fill in: where to write, and when
     * the document last changed.
     */
    const replacements = {
        // The date is a plain yyyy-MM-dd, which new Date() reads as
        // UTC midnight and so shows as the day before west of UTC.
        // Naming the time makes the browser read it locally.
        date: formatDate(new Date(`${updatedAt}T00:00:00`), currentLocale()),
        email: SUPPORT_EMAIL,
    };

    return (
        <article className="bg-background">
            <Head title={t(title)}>
                <meta name="description" content={t(description, replacements)} />
                <link rel="canonical" href={canonical} />
                <link rel="alternate" hrefLang="en" href={alternates.en} />
                <link rel="alternate" hrefLang="es" href={alternates.es} />
                <link rel="alternate" hrefLang="x-default" href={alternates.default} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={t(title)} />
                <meta property="og:description" content={t(description, replacements)} />
                <meta property="og:url" content={canonical} />
            </Head>

            <div className="mx-auto flex max-w-3xl flex-col gap-10 px-4 pt-28 pb-24 sm:px-6 sm:pt-32 sm:pb-32">
                {/** Title */}
                <header className="flex flex-col gap-3">
                    <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                        {t(title)}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t("Last updated on :date.", { date: replacements.date })}
                    </p>
                </header>

                {/** Sections */}
                <div className="flex flex-col gap-10">
                    {sections.map((section, index) => (
                        <section className="flex flex-col gap-3" key={section.heading}>
                            <h2 className="font-heading text-lg font-medium">
                                <span className="text-muted-foreground mr-2 tabular-nums">{index + 1}.</span>
                                {t(section.heading)}
                            </h2>
                            {section.paragraphs.map((paragraph) => (
                                <p className="text-muted-foreground leading-relaxed text-pretty" key={paragraph}>
                                    {t(paragraph, replacements)}
                                </p>
                            ))}
                        </section>
                    ))}
                </div>
            </div>
        </article>
    );
};
