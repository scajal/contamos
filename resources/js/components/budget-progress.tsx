import { useLaravelReactI18n } from "laravel-react-i18n";

import { cn, formatAmount, formatPercentage } from "@/lib/utils";
import { BudgetProgress as Progress } from "@/types/app/modules/core/finance/models/team/budget-period";

/**
 * The currency a budget's figures are expressed in, which is
 * all the formatter needs of it.
 */
type ProgressCurrency = {
    code: string;
    decimal_places: number;
    symbol: string;
};

type Props = {
    className?: string;
    currency: ProgressCurrency;
    progress: Progress;
};

/**
 * How far a month has gone into a tag's budget: a bar that fills
 * to the share spent, plus the numbers behind it. The bar stops
 * at full and turns to the destructive colour once the budget
 * is past, so overspending cannot be missed.
 */
export const BudgetProgress = ({ className, currency, progress }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The pot the month had to spend: the budgeted amount plus
     * whatever income was tagged into it.
     */
    const isOver = Number(progress.remaining) < 0;

    /**
     * The share of the pot that went, capped so the bar never
     * runs past its track.
     */
    const filled = Math.min(progress.percentage ?? 0, 100);

    /**
     * What is left, or how far past the budget the month has
     * gone, always written as a positive figure.
     */
    const rest = isOver ? progress.remaining.slice(1) : progress.remaining;

    return (
        <div className={cn("flex flex-col gap-1", className)}>
            <div
                aria-label={t("Budget")}
                aria-valuemax={100}
                aria-valuemin={0}
                aria-valuenow={Math.round(filled)}
                className="bg-muted h-1.5 w-full overflow-hidden rounded-full"
                role="progressbar"
            >
                <div
                    className={cn("h-full rounded-full transition-all", isOver ? "bg-destructive" : "bg-primary")}
                    style={{ width: `${filled}%` }}
                />
            </div>
            <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs tabular-nums">
                <span>
                    {t(":spent of :amount", {
                        spent: formatAmount(progress.spent, currentLocale(), currency, { symbol: false }),
                        amount: formatAmount(progress.amount, currentLocale(), currency, { symbol: false }),
                    })}
                </span>
                {/** Income tagged into the budget enlarges the pot. */}
                {Number(progress.income) > 0 && (
                    <span className="text-success">
                        {t("+:amount in", {
                            amount: formatAmount(progress.income, currentLocale(), currency, { symbol: false }),
                        })}
                    </span>
                )}
                <span className={cn(isOver && "text-destructive font-medium")}>
                    {isOver
                        ? t("over by :amount", {
                              amount: formatAmount(rest, currentLocale(), currency, { symbol: false }),
                          })
                        : t(":amount left", {
                              amount: formatAmount(rest, currentLocale(), currency, { symbol: false }),
                          })}
                </span>
                {progress.percentage !== null && <span>{formatPercentage(progress.percentage, currentLocale())}</span>}
            </div>
        </div>
    );
};
