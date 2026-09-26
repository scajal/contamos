import { Head, Link, router } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowLeftIcon } from "lucide-react";
import type React from "react";

import { Badge } from "@/components/ui/badge";
import { MarketingLayout } from "@/layouts/marketing/layout";
import { formatDate } from "@/lib/utils";
import { TableOfContents } from "@/pages/marketing/docs/_components/table-of-contents";
import type { Post } from "@/types/app/modules/marketing/post";

type PageProps = {
    blogUrl: string;
    feedUrl: string;
    logoUrl: string;
    markdownUrl: string;
    post: Post;
    versions: Record<string, string>;
};

/**
 * A post of the blog: its title, date and reading time, the post
 * itself rendered from its Markdown, and the sections it holds.
 */
const Show = ({ blogUrl, feedUrl, logoUrl, markdownUrl, post, versions }: PageProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The JSON-LD for the post.
     */
    const jsonLd = {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        headline: post.title,
        description: post.description,
        image: post.image,
        datePublished: post.published,
        dateModified: post.updated ?? post.published,
        inLanguage: currentLocale(),
        url: post.url,
        mainEntityOfPage: post.url,
        author: { "@type": "Organization", name: "Contamos" },
        publisher: { "@type": "Organization", name: "Contamos", logo: { "@type": "ImageObject", url: logoUrl } },
    };

    /**
     * Follow a link to another page of the blog or the docs without
     * reloading the shell. The Markdown is rendered on the server, so
     * its links are plain anchors, and this is where they become
     * Inertia visits.
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
            !/^\/[a-z]{2}\/(blog|docs)(\/|$)/.test(anchor.pathname) ||
            anchor.pathname.endsWith(".md") ||
            anchor.pathname.endsWith(".xml") ||
            (anchor.pathname === window.location.pathname && anchor.hash)
        ) {
            return;
        }

        event.preventDefault();
        router.visit(anchor.href);
    };

    return (
        <div className="mx-auto grid max-w-6xl gap-10 px-4 pt-10 pb-24 sm:px-6 sm:pt-16 sm:pb-32 xl:grid-cols-[minmax(0,1fr)_12rem]">
            <Head title={post.title}>
                <meta name="description" content={post.description} />
                {/** A draft is only ever opened to preview it, and must
                 * not end up in a search engine before it is out. */}
                {post.draft && <meta name="robots" content="noindex" />}
                <link rel="canonical" href={post.url} />
                {Object.entries(versions).map(([locale, url]) => (
                    <link rel="alternate" hrefLang={locale} href={url} key={locale} />
                ))}
                <link rel="alternate" hrefLang="x-default" href={versions.en ?? post.url} />
                <link rel="alternate" type="text/markdown" href={markdownUrl} />
                <link rel="alternate" type="application/rss+xml" title={t("Contamos blog")} href={feedUrl} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={post.title} />
                <meta property="og:description" content={post.description} />
                <meta property="og:url" content={post.url} />
                <meta property="og:image" content={post.image} />
                <meta property="article:published_time" content={post.published} />
                {post.updated && <meta property="article:modified_time" content={post.updated} />}
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
            </Head>

            {/** Post */}
            <article className="mx-auto w-full max-w-3xl min-w-0">
                <header className="flex flex-col gap-4 border-b pb-8">
                    <Link
                        className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1 text-sm font-medium transition-colors"
                        href={blogUrl}
                    >
                        <ArrowLeftIcon className="size-4" />
                        {t("Blog")}
                    </Link>
                    <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                        {post.title}
                    </h1>
                    <p className="text-muted-foreground text-base text-pretty">{post.description}</p>
                    <p className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <time dateTime={post.published}>
                            {formatDate(new Date(`${post.published}T00:00:00`), currentLocale())}
                        </time>
                        <span aria-hidden="true">·</span>
                        <span>{t(":minutes min read", { minutes: post.minutes })}</span>
                        {post.draft && <Badge variant="outline">{t("Draft")}</Badge>}
                    </p>
                </header>

                <div
                    className="typeset typeset-docs pt-8"
                    dangerouslySetInnerHTML={{ __html: post.html }}
                    onClick={onClick}
                />
            </article>

            {/** On this page */}
            <aside className="hidden xl:block">
                <div className="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto">
                    <TableOfContents headings={post.headings} />
                </div>
            </aside>
        </div>
    );
};

Show.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Show;
