import { Head, router } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import type React from "react";

import { ConnectionSteps, ExamplePrompts, ServerAddress } from "@/components/assistant-guide";
import { ProBadge } from "@/components/pro-badge";
import { DocsNavigation } from "@/layouts/marketing/_components/docs-navigation";
import { MarketingLayout } from "@/layouts/marketing/layout";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { DocsNavigationGroup } from "@/types/app/modules/marketing/docs";
import type { Document, DocumentHeading } from "@/types/app/modules/marketing/document";
import type { AssistantGuide } from "@/types/app/modules/mcp/assistant-guide";

import { PageActions } from "./_components/page-actions";
import { TableOfContents } from "./_components/table-of-contents";

type PageProps = {
    alternates: Alternates;
    assistants: AssistantGuide | null;
    document: Document;
    group: string;
    markdownUrl: string;
    navigation: Array<DocsNavigationGroup>;
    page: string;
};

/**
 * A page of the docs: the sidebar listing every page, the page
 * itself rendered from its Markdown, and the sections it holds.
 */
const Show = ({ alternates, assistants, document, group, markdownUrl, navigation, page }: PageProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The sections of the page, including the assistants guide the
     * AI assistants page closes with.
     */
    const headings: Array<DocumentHeading> = [
        ...document.headings,
        ...(assistants
            ? [
                  { id: "how-to-connect", text: t("How to connect"), level: 2 },
                  { id: "example-prompts", text: t("Example prompts"), level: 2 },
              ]
            : []),
    ];

    /**
     * The canonical URL of this page in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * Follow a link between docs pages without reloading the shell.
     * The Markdown is rendered on the server, so its links are plain
     * anchors, and this is where they become Inertia visits.
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
            !/^\/[a-z]{2}\/docs(\/|$)/.test(anchor.pathname) ||
            anchor.pathname.endsWith(".md") ||
            (anchor.pathname === window.location.pathname && anchor.hash)
        ) {
            return;
        }

        event.preventDefault();
        router.visit(anchor.href);
    };

    return (
        <div className="mx-auto grid max-w-7xl gap-10 px-4 pt-8 pb-24 sm:px-6 sm:pt-10 sm:pb-32 lg:grid-cols-[13rem_minmax(0,1fr)] xl:grid-cols-[13rem_minmax(0,1fr)_12rem]">
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
            </Head>

            {/** Sidebar */}
            <aside className="hidden lg:block">
                <div className="sticky top-24 -ml-2 max-h-[calc(100vh-7rem)] overflow-y-auto pb-6">
                    <DocsNavigation current={page} navigation={navigation} />
                </div>
            </aside>

            {/** Page */}
            <article className="mx-auto w-full max-w-3xl min-w-0">
                <header className="flex flex-col gap-3 border-b pb-8">
                    <p className="text-muted-foreground text-sm font-medium">{group}</p>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                            {document.title}
                        </h1>
                        {document.pro && <ProBadge className="h-5 px-2 text-xs" />}
                    </div>
                    <p className="text-muted-foreground text-base text-pretty">{document.description}</p>
                    <PageActions markdownUrl={markdownUrl} />
                </header>

                <div
                    className="typeset typeset-docs pt-8"
                    dangerouslySetInnerHTML={{ __html: document.html }}
                    onClick={onClick}
                />

                {/** The connection steps and example prompts the app's own
                 * AI assistants page shows too. */}
                {assistants && (
                    <div className="typeset typeset-docs mt-9">
                        <h2 id="how-to-connect">{t("How to connect")}</h2>
                        <p>
                            {t("You sign in once; the assistant then acts as you, with your role on each team.")}{" "}
                            {t("Paste this address into your assistant to connect it.")}
                        </p>
                        <div className="not-typeset mt-5 flex flex-col gap-4">
                            <ServerAddress url={assistants.server_url} />
                            <ConnectionSteps guides={assistants.guides} />
                        </div>
                        <h2 id="example-prompts">{t("Example prompts")}</h2>
                        <p>{t("Copy one into your assistant once it is connected.")}</p>
                        <div className="not-typeset mt-5">
                            <ExamplePrompts prompts={assistants.prompts} />
                        </div>
                    </div>
                )}
            </article>

            {/** On this page */}
            <aside className="hidden xl:block">
                <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
                    <TableOfContents headings={headings} />
                </div>
            </aside>
        </div>
    );
};

Show.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Show;
