import { Head, Link } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowRightIcon } from "lucide-react";
import type React from "react";

import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from "@/components/ui/empty";
import { MarketingLayout } from "@/layouts/marketing/layout";
import { formatDate } from "@/lib/utils";
import type { Alternates } from "@/types/app/modules/marketing/alternates";
import type { PostSummary } from "@/types/app/modules/marketing/post";

type PageProps = {
    alternates: Alternates;
    feedUrl: string;
    imageUrl: string;
    posts: Array<PostSummary>;
};

/**
 * The blog: every published post in the reader's language, newest
 * first, each with its date, reading time and summary.
 */
const Index = ({ alternates, feedUrl, imageUrl, posts }: PageProps) => {
    /**
     * Translate the provided string to the current locale.
     */
    const { t, currentLocale } = useLaravelReactI18n();

    /**
     * The canonical URL of the blog in the current locale.
     */
    const canonical = alternates[currentLocale() as "en" | "es"] ?? alternates.en;

    /**
     * What the blog is about, shown under its title and given to
     * search engines as its description.
     */
    const description = t(
        "Notes on keeping track of money with the people you share it with, in every currency it comes in.",
    );

    /**
     * The JSON-LD for the blog and the posts it lists.
     */
    const jsonLd = {
        "@context": "https://schema.org",
        "@type": "Blog",
        name: t("Contamos blog"),
        description: description,
        url: canonical,
        inLanguage: currentLocale(),
        blogPost: posts.map((post) => ({
            "@type": "BlogPosting",
            headline: post.title,
            description: post.description,
            datePublished: post.published,
            url: post.url,
        })),
    };

    return (
        <div className="mx-auto flex max-w-6xl flex-col gap-12 px-4 pt-10 pb-24 sm:px-6 sm:pt-16 sm:pb-32">
            <Head title={t("Blog")}>
                <meta name="description" content={description} />
                <link rel="canonical" href={canonical} />
                <link rel="alternate" hrefLang="en" href={alternates.en} />
                <link rel="alternate" hrefLang="es" href={alternates.es} />
                <link rel="alternate" hrefLang="x-default" href={alternates.default} />
                <link rel="alternate" type="application/rss+xml" title={t("Contamos blog")} href={feedUrl} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={t("Contamos blog")} />
                <meta property="og:description" content={description} />
                <meta property="og:url" content={canonical} />
                <meta property="og:image" content={imageUrl} />
                <meta name="twitter:card" content="summary_large_image" />
                <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
            </Head>

            {/** Title */}
            <header className="flex flex-col gap-3 border-b pb-8">
                <h1 className="font-heading text-3xl leading-tight font-semibold tracking-tight text-balance sm:text-4xl">
                    {t("Blog")}
                </h1>
                <p className="text-muted-foreground max-w-2xl text-base text-pretty">{description}</p>
            </header>

            {/** Posts */}
            {posts.length === 0 ? (
                <Empty>
                    <EmptyHeader>
                        <EmptyTitle>{t("No posts yet")}</EmptyTitle>
                        <EmptyDescription>{t("The first one is on its way.")}</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <ol className="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.map((post) => (
                        <li key={post.slug}>
                            <article className="group flex h-full flex-col gap-2">
                                <p className="text-muted-foreground text-sm">
                                    <time dateTime={post.published}>
                                        {formatDate(new Date(`${post.published}T00:00:00`), currentLocale())}
                                    </time>
                                    {" · "}
                                    {t(":minutes min read", { minutes: post.minutes })}
                                </p>
                                <h2 className="font-heading text-xl leading-snug font-semibold tracking-tight text-balance">
                                    <Link className="hover:text-primary transition-colors" href={post.url}>
                                        {post.title}
                                    </Link>
                                </h2>
                                <p className="text-muted-foreground flex-1 text-pretty">{post.description}</p>
                                <Link
                                    className="text-foreground inline-flex w-fit items-center gap-1 text-sm font-medium"
                                    href={post.url}
                                    aria-label={t("Read “:title”", { title: post.title })}
                                >
                                    {t("Read the post")}
                                    <ArrowRightIcon className="size-4 transition-transform group-hover:translate-x-0.5" />
                                </Link>
                            </article>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <MarketingLayout>{page}</MarketingLayout>;

export default Index;
