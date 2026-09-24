/**
 * One page of the docs sidebar, as listed by
 * App\Modules\Marketing\Http\Controllers\DocsController.
 */
export interface DocsNavigationPage {
    page: string;
    label: string;
    pro: boolean;
    url: string;
}

/**
 * One heading of the docs sidebar with the pages under it,
 * in the order of App\Modules\Marketing\Enums\Docs\Page.
 */
export interface DocsNavigationGroup {
    label: string;
    pages: Array<DocsNavigationPage>;
}
