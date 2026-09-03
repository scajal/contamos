import { router } from "@inertiajs/react";
import debounce from "lodash/debounce";
import { useEffect, useMemo, useRef, useState } from "react";

/**
 * The shape of the filters an index page keeps in sync with the URL.
 */
export type FilterValues = Record<string, string | null>;

type Options<T extends FilterValues> = {
    /**
     * The filters the page was rendered with (from the `filters` prop).
     */
    initial: T;

    /**
     * Build the index URL for the given query (usually a Wayfinder route helper).
     */
    url: (query: { filter?: Record<string, string> }) => string;

    /**
     * The props to reload when the filters change.
     */
    only: string[];

    /**
     * The props to reset when the filters change (required for `Inertia::scroll()` listings).
     */
    reset?: string[];
};

/**
 * Drop empty values so the query string only carries active filters.
 */
const compact = (filters: FilterValues): Record<string, string> =>
    Object.fromEntries(
        Object.entries(filters).filter((entry): entry is [string, string] => entry[1] !== null && entry[1] !== ""),
    );

/**
 * Keep an index page's filters in the URL and reload the listing through a partial visit.
 */
export function useFilters<T extends FilterValues>(options: Options<T>) {
    /**
     * The filters to display in the UI.
     */
    const [filters, setState] = useState<T>(options.initial);

    /**
     * Always call the latest options from the debounced visit.
     */
    const optionsRef = useRef(options);

    /**
     * Update the options reference when the options change.
     */
    useEffect(() => {
        optionsRef.current = options;
    });

    /**
     * Visit the index URL with the given filters.
     */
    const visit = (next: T) => {
        // Get the options from the reference.
        const { url, only, reset } = optionsRef.current;

        // Visit the index URL with the given filters.
        router.get(
            url({ filter: compact(next) }),
            {},
            { only, reset, preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /**
     * Debounced visit for free-text inputs.
     */
    const debouncedVisit = useMemo(() => debounce(visit, 300), []);

    /**
     * Cancel the debounced visit when the component unmounts.
     */
    useEffect(() => () => debouncedVisit.cancel(), [debouncedVisit]);

    /**
     * Merge the given values into the filters and reload the listing.
     */
    const setFilters = (values: Partial<T>, { debounced = false }: { debounced?: boolean } = {}) => {
        // Merge the given values into the filters.
        const next = { ...filters, ...values };

        // Update the filters in the state.
        setState(next);

        if (debounced) {
            debouncedVisit(next);
        } else {
            debouncedVisit.cancel();
            visit(next);
        }
    };

    /**
     * Clear every filter and reload the listing.
     */
    const clear = () => {
        const next = Object.fromEntries(Object.keys(filters).map((key) => [key, null])) as T;

        setState(next);
        debouncedVisit.cancel();
        visit(next);
    };

    /**
     * Whether any filter is active.
     */
    const isFiltered = Object.keys(compact(filters)).length > 0;

    return { clear, filters, isFiltered, setFilters };
}
