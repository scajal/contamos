import { useLaravelReactI18n } from "laravel-react-i18n";
import { CheckIcon, MinusIcon } from "lucide-react";

import { isSwitchValue, type PlanValue } from "@/lib/billing";
import { cn } from "@/lib/utils";
import { Feature } from "@/types/app/modules/billing/enums/feature";
import { Plan } from "@/types/app/modules/billing/enums/plan";

/**
 * A row of the comparison: the feature, its limit on each plan,
 * and optionally how much of it the team is using.
 */
export type PlanTableRow = {
    feature: Feature;
    label: string;
    free: PlanValue;
    pro: PlanValue;
    usage?: number | null;
};

type Props = {
    rows: Array<PlanTableRow>;
    /** The plan the team is on. Its column shows the usage and is the emphasised one. */
    current?: Plan;
    /** A feature to single out, such as the one whose limit was just hit. */
    highlight?: Feature | null;
};

/**
 * The Free versus Pro comparison, shared by the marketing page and the
 * upgrade modal so the promise and the product read the same way.
 *
 * One column is emphasised the whole way down for the eye to follow:
 * the plan the team is on when there is one, and Pro when the reader
 * has no team yet. A switch feature is a mark rather than a sentence,
 * with the words kept for a screen reader, and a quota is its number.
 *
 * Below `md` the columns become one block per plan, because a table of
 * three columns on a phone is a table nobody scrolls sideways.
 */
export const PlanTable = ({ rows, current, highlight }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The columns, in the order they are compared. Pro carries the
     * emphasis unless the team is on Free and is looking at its own.
     */
    const columns: Array<{ featured: boolean; key: Plan; label: string }> = [
        { featured: current === "free", key: "free", label: t("Free") },
        { featured: current !== "free", key: "pro", label: t("Pro") },
    ];

    /**
     * What a plan gives for a row, as usage against the limit when the
     * team is on that plan and the figure is known.
     */
    const value = (plan: Plan, row: PlanTableRow) => {
        const given = plan === "free" ? row.free : row.pro;

        if (current !== plan || row.usage === undefined || row.usage === null || isSwitchValue(given)) {
            return <Value value={given} />;
        }

        return (
            <span className={cn("tabular-nums", typeof given === "number" && row.usage > given && "text-destructive")}>
                {row.usage}
                <span className="text-muted-foreground font-normal">
                    {" / "}
                    {given === null ? t("Unlimited") : given}
                </span>
            </span>
        );
    };

    return (
        <div>
            <table className="w-full border-separate border-spacing-0 text-left max-md:hidden">
                <caption className="sr-only">{t("Plans")}</caption>
                <thead>
                    <tr>
                        <th className="w-1/2 pb-3" scope="col">
                            <span className="sr-only">{t("Feature")}</span>
                        </th>
                        {columns.map((column) => (
                            <th
                                className={cn(
                                    "font-heading w-1/4 rounded-t-xl px-4 pt-4 pb-3 text-sm font-semibold tracking-tight",
                                    column.featured ? "bg-muted/50" : "text-muted-foreground",
                                )}
                                key={column.key}
                                scope="col"
                            >
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={row.feature}>
                            <th
                                className={cn(
                                    "border-border border-t py-3 pr-4 text-sm font-normal",
                                    row.feature === highlight && "font-medium",
                                )}
                                scope="row"
                            >
                                {row.label}
                            </th>
                            {columns.map((column) => (
                                <td
                                    className={cn(
                                        "border-border border-t px-4 py-3 text-sm",
                                        column.featured ? "bg-muted/50 font-medium" : "text-muted-foreground",
                                        column.featured && index === rows.length - 1 && "rounded-b-xl",
                                        row.feature === highlight && "text-foreground font-medium",
                                    )}
                                    key={column.key}
                                >
                                    {value(column.key, row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>

            {/** A phone reads one plan at a time. */}
            <div className="flex flex-col gap-3 md:hidden">
                {columns.map((column) => (
                    <section className={cn("rounded-xl border p-4", column.featured && "bg-muted/40")} key={column.key}>
                        <h3 className="font-heading text-sm font-semibold tracking-tight">{column.label}</h3>
                        <dl className="mt-1 flex flex-col">
                            {rows.map((row) => (
                                <div
                                    className={cn(
                                        "border-border flex items-center justify-between gap-4 border-t py-2.5",
                                        row.feature === highlight && "font-medium",
                                    )}
                                    key={row.feature}
                                >
                                    <dt className="text-muted-foreground text-sm">{row.label}</dt>
                                    <dd className="shrink-0 text-sm font-medium">{value(column.key, row)}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ))}
            </div>
        </div>
    );
};

/**
 * What a plan gives for one feature. A quota reports its limit, where
 * null means no limit at all. A switch reports whether the plan
 * includes it, which reads faster as a mark than as a sentence, with
 * the words kept for a screen reader.
 */
const Value = ({ value }: { value: PlanValue }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    if (!isSwitchValue(value)) {
        return <span className="tabular-nums">{value === null ? t("Unlimited") : String(value)}</span>;
    }

    return value ? (
        <span className="text-foreground">
            <CheckIcon aria-hidden="true" className="inline size-5" />
            <span className="sr-only">{t("Included")}</span>
        </span>
    ) : (
        <span className="text-muted-foreground/50">
            <MinusIcon aria-hidden="true" className="inline size-5" />
            <span className="sr-only">{t("Not included")}</span>
        </span>
    );
};
