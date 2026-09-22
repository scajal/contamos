/**
 * One titled part of a legal document. Both the heading and the
 * paragraphs are translation keys, resolved with the document's own
 * replacements so the support address and the date it last changed
 * are written in one place.
 */
export interface LegalSection {
    heading: string;
    paragraphs: Array<string>;
}
