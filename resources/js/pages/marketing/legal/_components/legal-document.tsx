import { Head } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";

import { formatDate } from "@/lib/utils";
import { TableOfContents } from "@/pages/marketing/docs/_components/table-of-contents";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { Document } from "@/types/app/modules/marketing/document";

interface Props {
    alternates: Alternates;
    document: Document;
}

/**
 * The shell the three legal documents share: their head tags, the
 * title with the date it last changed, the document itself, written
 * in Markdown per locale and rendered on the server, and the sections
 * it holds beside it.
 */
export const LegalDocument = ({ alternates, document }: Props) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of this document in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    return (
        <div className="bg-background">
            <Head title={document.title}>
                <meta name="description" content={document.description} />
                <link rel="canonical" href={canonical} />
                <link rel="alternate" hrefLang="en" href={alternates.en} />
                <link rel="alternate" hrefLang="es" href={alternates.es} />
                <link rel="alternate" hrefLang="x-default" href={alternates.default} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={document.title} />
                <meta property="og:description" content={document.description} />
                <meta property="og:url" content={canonical} />
            </Head>

            <div className="mx-auto grid max-w-6xl gap-10 px-4 pt-28 pb-24 sm:px-6 sm:pt-32 sm:pb-32 lg:grid-cols-[minmax(0,1fr)_14rem]">
                <article className="flex w-full max-w-3xl min-w-0 flex-col gap-10">
                    {/** Title */}
                    <header className="flex flex-col gap-3">
                        <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                            {document.title}
                        </h1>
                        {document.updated && (
                            <p className="text-muted-foreground text-sm">
                                {t("Last updated on :date.", {
                                    // The date is a plain yyyy-MM-dd, which new Date()
                                    // reads as UTC midnight and so shows as the day
                                    // before west of UTC. Naming the time makes the
                                    // browser read it locally.
                                    date: formatDate(new Date(`${document.updated}T00:00:00`), currentLocale()),
                                })}
                            </p>
                        )}
                    </header>

                    {/** Document */}
                    <div className="typeset typeset-docs" dangerouslySetInnerHTML={{ __html: document.html }} />
                </article>

                {/** On this page */}
                <aside className="hidden lg:block">
                    <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
                        <TableOfContents headings={document.headings} />
                    </div>
                </aside>
            </div>
        </div>
    );
};
