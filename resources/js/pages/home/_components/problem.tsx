import { useLaravelReactI18n } from "laravel-react-i18n";

/**
 * The problem statement: large type, no card, plain prose.
 */
export const Problem = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section id="product" className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <p className="max-w-[42ch] text-2xl leading-snug font-medium text-balance md:text-3xl">
                {t(
                    "Shared money gets messy fast. One of you pays rent in euros, the other gets paid in dollars, and the savings sit in a third currency. Converting everything into one number hides what actually happened.",
                )}
            </p>
        </section>
    );
};
