import { useLaravelReactI18n } from "laravel-react-i18n";

import { useBalances } from "@/hooks/use-balances";
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
 * is past, so overspending cannot be missed. A warning
 * threshold shows as a faint tick on the bar.
 */
export const BudgetProgress = ({ className, currency, progress }: Props) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Mask the figures while balances are hidden. A budget's numbers are
     * balances too, even written into a sentence.
     */
    const mask = useBalances().mask;

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
                className="bg-muted relative h-1.5 w-full overflow-hidden rounded-full"
                role="progressbar"
            >
                <div
                    className={cn("h-full rounded-full transition-all", isOver ? "bg-destructive" : "bg-primary")}
                    style={{ width: `${filled}%` }}
                />
                {/** A faint tick marks where the team asked to be warned. */}
                {progress.warning_threshold !== null && (
                    <div
                        aria-hidden
                        className="bg-foreground/30 absolute inset-y-0 w-px"
                        style={{ left: `${progress.warning_threshold}%` }}
                    />
                )}
            </div>
            <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs tabular-nums">
                <span>
                    {t(":spent of :amount", {
                        spent: mask(formatAmount(progress.spent, currentLocale(), currency, { symbol: false })),
                        amount: mask(formatAmount(progress.amount, currentLocale(), currency, { symbol: false })),
                    })}
                </span>
                {/** Income tagged into the budget enlarges the pot. */}
                {Number(progress.income) > 0 && (
                    <span className="text-success">
                        {t("+:amount in", {
                            amount: mask(formatAmount(progress.income, currentLocale(), currency, { symbol: false })),
                        })}
                    </span>
                )}
                <span className={cn(isOver && "text-destructive font-medium")}>
                    {isOver
                        ? t("over by :amount", {
                              amount: mask(formatAmount(rest, currentLocale(), currency, { symbol: false })),
                          })
                        : t(":amount left", {
                              amount: mask(formatAmount(rest, currentLocale(), currency, { symbol: false })),
                          })}
                </span>
                {progress.percentage !== null && <span>{formatPercentage(progress.percentage, currentLocale())}</span>}
                {progress.warning_threshold !== null && (
                    <span>
                        {t("Warning at :percentage", {
                            percentage: formatPercentage(progress.warning_threshold, currentLocale()),
                        })}
                    </span>
                )}
            </div>
        </div>
    );
};
