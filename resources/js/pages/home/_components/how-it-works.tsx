import { useLaravelReactI18n } from "laravel-react-i18n";

const STEPS = ["Create your team", "Add your accounts and currencies", "Log transactions as they happen"] as const;

/**
 * The real 3-step rail, as a dark band: the scroll's second peak
 * after the hero, with large ghost numerals carrying the sequence.
 */
export const HowItWorks = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="bg-foreground text-background">
            <div className="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
                <h2 className="font-heading text-3xl font-semibold tracking-tight text-balance md:text-5xl">
                    {t("Up and running in three steps")}
                </h2>

                <ol className="mt-14 grid gap-10 md:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <li key={step} className="flex flex-col gap-2">
                            <span className="font-heading text-primary text-6xl leading-none font-semibold tabular-nums">
                                {index + 1}
                            </span>
                            <p className="text-background/90 text-lg font-medium">{t(step)}</p>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
};
