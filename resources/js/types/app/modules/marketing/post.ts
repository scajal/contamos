import type { DocumentHeading } from "./document";

/**
 * JSON shape of a blog post as the index lists it, built by
 * App\Modules\Marketing\Objects\Post::summary().
 */
export interface PostSummary {
    slug: string;
    title: string;
    description: string;
    published: string;
    minutes: number;
    url: string;
}

/**
 * JSON shape of a blog post rendered on the server, built by
 * App\Modules\Marketing\Objects\Post.
 */
export interface Post extends PostSummary {
    updated: string | null;
    image: string;
    draft: boolean;
    html: string;
    headings: Array<DocumentHeading>;
}
