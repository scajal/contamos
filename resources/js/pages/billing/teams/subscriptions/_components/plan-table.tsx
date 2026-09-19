import { useLaravelReactI18n } from "laravel-react-i18n";

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
    free: number | boolean | null;
    pro: number | boolean | null;
    usage?: number | null;
};

type Props = {
    rows: Array<PlanTableRow>;
    /** The plan the team is on. Its column shows the usage and is emphasised. */
    current?: Plan;
    /** A feature to single out, such as the one whose limit was just hit. */
    highlight?: Feature | null;
};

/**
 * The Free versus Pro comparison shared by the upgrade modal and
 * the billing page. Quiet by design: even padding, tabular numbers,
 * and one tinted row at most.
 */
export const PlanTable = ({ rows, current, highlight }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Format a plan limit for display. A switch feature reports
     * whether the plan includes it instead of a number.
     */
    const limit = (value: number | boolean | null) => {
        if (typeof value === "boolean") {
            return value ? t("Included") : t("Not included");
        }

        return value === null ? t("Unlimited") : String(value);
    };

    /**
     * Format a plan cell. When it is the team's current plan and
     * the usage is known, show usage against the limit.
     */
    const cell = (plan: Plan, row: PlanTableRow) => {
        const value = plan === "free" ? row.free : row.pro;

        if (current !== plan || row.usage === undefined || row.usage === null || typeof value === "boolean") {
            return limit(value);
        }

        return value === null ? (
            <span>
                {row.usage}
                <span className="text-muted-foreground font-normal"> / {t("Unlimited")}</span>
            </span>
        ) : (
            <span className={cn(row.usage > value && "text-destructive")}>
                {row.usage}
                <span className="text-muted-foreground font-normal"> / {value}</span>
            </span>
        );
    };

    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="bg-muted/50 text-muted-foreground text-xs">
                        <th className="px-3 py-2 text-left font-normal sm:px-4" scope="col">
                            <span className="sr-only">{t("Feature")}</span>
                        </th>
                        <th
                            className={cn(
                                "w-16 px-2 py-2 text-right font-normal sm:w-24 sm:px-4",
                                current === "free" && "text-foreground font-medium",
                            )}
                            scope="col"
                        >
                            {t("Free")}
                        </th>
                        <th
                            className={cn(
                                "w-20 px-3 py-2 text-right font-normal sm:w-28 sm:px-4",
                                (current === "pro" || current === undefined) && "text-foreground font-medium",
                            )}
                            scope="col"
                        >
                            {t("Pro")}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const isHighlighted = row.feature === highlight;

                        return (
                            <tr
                                key={row.feature}
                                className={cn("border-t", isHighlighted && "bg-foreground/[0.04] font-medium")}
                            >
                                <td className="px-3 py-3 text-left sm:px-4">{row.label}</td>
                                <td
                                    className={cn(
                                        "px-2 py-3 text-right tabular-nums sm:px-4",
                                        current === "free" ? "font-medium" : "text-muted-foreground",
                                    )}
                                >
                                    {cell("free", row)}
                                </td>
                                <td
                                    className={cn(
                                        "px-3 py-3 text-right tabular-nums sm:px-4",
                                        current === "free" && "text-muted-foreground",
                                        current === "pro" && "font-medium",
                                    )}
                                >
                                    {cell("pro", row)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
};
