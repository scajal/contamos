import { useLaravelReactI18n } from "laravel-react-i18n";

const STEPS = ["Create your team", "Add your accounts and currencies", "Log transactions as they happen"] as const;

/**
 * The real 3-step rail: create a team, add accounts and
 * currencies, then log transactions.
 */
export const HowItWorks = () => {
    /**
     * Translate the provided string to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
            <h2 className="font-heading text-3xl font-semibold tracking-tight md:text-5xl">
                {t("Up and running in three steps")}
            </h2>

            <ol className="mt-12 grid gap-8 md:grid-cols-3">
                {STEPS.map((step, index) => (
                    <li key={step} className="flex flex-col gap-3 border-t pt-6">
                        <span className="font-heading text-muted-foreground text-sm tabular-nums">
                            {String(index + 1).padStart(2, "0")}
                        </span>
                        <p className="text-lg font-medium">{t(step)}</p>
                    </li>
                ))}
            </ol>
        </section>
    );
};
