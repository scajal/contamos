/**
 * JSON shape of the entity behind the legal documents, built by
 * App\Modules\Marketing\Http\Controllers\LegalController from config/legal.php.
 */
export interface Legal {
    address: string;
    entity: string;
    jurisdiction: string;
    reviewed: boolean;
    updatedAt: string;
}

/**
 * One titled part of a legal document. Both the heading and the
 * paragraphs are translation keys, resolved with the document's own
 * replacements so the entity, the jurisdiction and the support address
 * are written in one place.
 */
export interface LegalSection {
    heading: string;
    paragraphs: Array<string>;
}
