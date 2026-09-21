import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { AlertTriangleIcon } from "lucide-react";

import { formatDate } from "@/lib/utils";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Legal, LegalSection } from "@/types/app/modules/marketing/legal";

interface Props {
    alternates: Alternates;
    description: string;
    legal: Legal;
    sections: Array<LegalSection>;
    support: string;
    title: string;
}

/**
 * The shell the three legal documents share: their head tags, the
 * notice that the text is still a draft, the title with the date it
 * last changed, and the numbered sections themselves.
 *
 * Every string reaching it is a translation key, resolved here with one
 * set of replacements, so the entity, the jurisdiction and the support
 * address are written into the copy from configuration rather than
 * repeated in each document.
 */
export const LegalDocument = ({ alternates, description, legal, sections, support, title }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of this document in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * What every document needs to name: who it binds, under which
     * law, where to write and when it last changed.
     */
    const replacements = {
        // The date is a plain yyyy-MM-dd, which new Date() reads as
        // UTC midnight and so shows as the day before west of UTC.
        // Naming the time makes the browser read it locally.
        date: formatDate(new Date(`${legal.updatedAt}T00:00:00`), currentLocale()),
        email: support,
        entity: legal.entity,
        jurisdiction: legal.jurisdiction,
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
                {/** Draft notice */}
                {legal.reviewed ? null : (
                    <div className="flex gap-3 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4">
                        <AlertTriangleIcon className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                        <p className="text-sm leading-relaxed">
                            {t(
                                "This text is a draft. It has not been reviewed by a lawyer yet and is published so its structure can be checked. Do not rely on it.",
                            )}
                        </p>
                    </div>
                )}

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
