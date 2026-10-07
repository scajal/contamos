import { Head, Link, router } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";
import type React from "react";

import { Button } from "@/components/ui/button";
import { MarketingLayout } from "@/layouts/marketing/layout";
import { formatDate } from "@/lib/utils";
import { login } from "@/routes";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { ComparisonLink } from "@/types/app/modules/marketing/compare";
import type { Document } from "@/types/app/modules/marketing/document";

import { PageActions } from "../docs/_components/page-actions";
import { TableOfContents } from "../docs/_components/table-of-contents";

type PageProps = {
    alternates: Alternates;
    competitor: string;
    dashboardUrl: string | null;
    document: Document;
    imageUrl: string;
    markdownUrl: string;
    name: string;
    others: Array<ComparisonLink>;
};

/**
 * A comparison of Contamos with another product: the page itself
 * rendered from its Markdown, the sections it holds, and the way
 * into the application for a reader who has decided.
 */
const Show = ({ alternates, dashboardUrl, document, imageUrl, markdownUrl, name, others }: PageProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of this page in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * Follow a link to the docs, the blog or another comparison
     * without reloading the shell. The Markdown is rendered on the
     * server, so its links are plain anchors, and this is where they
     * become Inertia visits.
     */
    const onClick = (event: React.MouseEvent<HTMLDivElement>) => {
        const anchor = (event.target as HTMLElement).closest("a");

        if (
            !anchor ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            anchor.target ||
            anchor.origin !== window.location.origin ||
            !/^\/[a-z]{2}\/(blog|compare|docs)(\/|$)/.test(anchor.pathname) ||
            anchor.pathname.endsWith(".md") ||
            (anchor.pathname === window.location.pathname && anchor.hash)
        ) {
            return;
        }

        event.preventDefault();
        router.visit(anchor.href);
    };

    return (
        <div className="mx-auto grid max-w-6xl gap-10 px-4 pt-10 pb-24 sm:px-6 sm:pt-16 sm:pb-32 lg:grid-cols-[minmax(0,1fr)_14rem]">
            <Head title={document.title}>
                <meta name="description" content={document.description} />
                <link rel="canonical" href={canonical} />
                <link rel="alternate" hrefLang="en" href={alternates.en} />
                <link rel="alternate" hrefLang="es" href={alternates.es} />
                <link rel="alternate" hrefLang="x-default" href={alternates.default} />
                <link rel="alternate" type="text/markdown" href={markdownUrl} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={document.title} />
                <meta property="og:description" content={document.description} />
                <meta property="og:url" content={canonical} />
                <meta property="og:image" content={imageUrl} />
                <meta name="twitter:card" content="summary_large_image" />
            </Head>

            {/** Page */}
            <article className="w-full max-w-3xl min-w-0">
                <header className="flex flex-col gap-3 border-b pb-8">
                    <p className="text-muted-foreground text-sm font-medium">
                        {t("Contamos compared with :name", { name })}
                    </p>
                    <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                        {document.title}
                    </h1>
                    <p className="text-muted-foreground text-base text-pretty">{document.description}</p>
                    {document.updated && (
                        <p className="text-muted-foreground text-sm">
                            {t("Last checked :date", {
                                date: formatDate(new Date(`${document.updated}T00:00:00`), currentLocale()),
                            })}
                        </p>
                    )}
                    <PageActions markdownUrl={markdownUrl} />
                </header>

                <div
                    className="typeset typeset-docs pt-8"
                    dangerouslySetInnerHTML={{ __html: document.html }}
                    onClick={onClick}
                />

                {/** The way on into the application, which is the one
                 * thing a reader who has finished comparing is asked
                 * to do. */}
                <aside className="bg-muted/40 mt-16 flex flex-col items-start gap-4 rounded-xl border p-6 sm:p-8">
                    <h2 className="font-heading text-xl font-semibold tracking-tight">{t("Try the shared ledger")}</h2>
                    <p className="text-muted-foreground text-pretty">
                        {t("One ledger for everyone you share money with, in every currency it lives in.")}
                    </p>
                    <div className="flex flex-col items-start gap-2">
                        <Button nativeButton={false} render={<a href={dashboardUrl ?? login.url()} />} size="lg">
                            {dashboardUrl ? t("Go to dashboard") : t("Start free")} <ArrowRightIcon />
                        </Button>
                        {!dashboardUrl && (
                            <p className="text-muted-foreground text-sm">{t("Free plan. No card required.")}</p>
                        )}
                    </div>
                </aside>

                {/** Other comparisons */}
                {others.length > 0 && (
                    <nav aria-labelledby="other-comparisons" className="mt-12 border-t pt-8">
                        <h2 id="other-comparisons" className="text-muted-foreground text-sm font-medium">
                            {t("Other comparisons")}
                        </h2>
                        <ul className="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                            {others.map((other) => (
                                <li key={other.url}>
                                    <Link
                                        className="font-heading hover:text-primary font-semibold transition-colors"
                                        href={other.url}
                                    >
                                        {t("Contamos vs :name", { name: other.name })}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
            </article>

            {/** On this page */}
            <aside className="hidden lg:block">
                <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
                    <TableOfContents headings={document.headings} />
                </div>
            </aside>
        </div>
    );
};

Show.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Show;
