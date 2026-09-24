/**
 * One entry of a document's table of contents: a second or third
 * level heading and the anchor it carries.
 */
export interface DocumentHeading {
    id: string;
    text: string;
    level: number;
}

/**
 * JSON shape of a Markdown document rendered on the server, built by
 * App\Modules\Marketing\Objects\Document for the legal and docs pages.
 */
export interface Document {
    title: string;
    description: string;
    pro: boolean;
    updated: string | null;
    html: string;
    headings: Array<DocumentHeading>;
}
