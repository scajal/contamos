/**
 * Represents a single pagination link in Laravel's paginator.
 */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * TypeScript representation of Laravel's LengthAwarePaginator.
 * This type matches the structure returned by Laravel's paginate() method
 * when serialized to JSON for Inertia.js.
 *
 * @template T - The type of items in the paginated collection
 *
 * @example
 * ```typescript
 * interface DevicesPageProps {
 *   devices: LengthAwarePaginator<Device>;
 * }
 * ```
 */
export interface LengthAwarePaginator<T> {
    /**
     * The items for the current page.
     */
    data: Array<T>;

    /**
     * The current page number (1-indexed).
     */
    current_page: number;

    /**
     * The URL for the first page.
     */
    first_page_url: string;

    /**
     * The index of the first item on the current page (1-indexed).
     * Null if there are no items.
     */
    from: number | null;

    /**
     * The last page number.
     */
    last_page: number;

    /**
     * The URL for the last page.
     */
    last_page_url: string;

    /**
     * Array of pagination links for rendering pagination controls.
     * Includes links for previous, next, and numbered pages.
     */
    links: Array<PaginationLink>;

    /**
     * The URL for the next page.
     * Null if there is no next page.
     */
    next_page_url: string | null;

    /**
     * The base path for pagination URLs.
     */
    path: string;

    /**
     * The number of items per page.
     */
    per_page: number;

    /**
     * The URL for the previous page.
     * Null if there is no previous page.
     */
    prev_page_url: string | null;

    /**
     * The index of the last item on the current page (1-indexed).
     * Null if there are no items.
     */
    to: number | null;

    /**
     * The total number of items across all pages.
     */
    total: number;
}
