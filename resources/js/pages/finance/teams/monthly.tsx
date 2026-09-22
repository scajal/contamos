import { Deferred, Link, router, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { addMonths, endOfMonth, format, isAfter, isBefore, parse, startOfMonth, subMonths } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowDownIcon,
    ArrowUpIcon,
    CalendarIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    LockIcon,
    PlusIcon,
    WalletIcon,
} from "lucide-react";
import { useState } from "react";
import { Bar, CartesianGrid, Cell, ComposedChart, Line, Pie, PieChart, XAxis } from "recharts";

import { BudgetProgress } from "@/components/budget-progress";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from "@/components/ui/chart";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemMedia } from "@/components/ui/item";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useBalances } from "@/hooks/use-balances";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount, formatMonth, formatPercentage } from "@/lib/utils";
import subscriptions from "@/routes/billing/teams/subscriptions";
import accounts from "@/routes/finance/teams/accounts";
import monthly from "@/routes/finance/teams/monthly";
import transactions from "@/routes/finance/teams/transactions";
import { BudgetProgress as BudgetProgressFigures } from "@/types/app/modules/core/finance/models/team/budget-period";
import { Team } from "@/types/app/modules/core/identity/models/team";

/**
 * The month format used in the URL and in every
 * figure the page receives.
 */
const MONTH_FORMAT = "yyyy-MM";

/**
 * The date format the transactions index filters
 * expect.
 */
const DATE_FORMAT = "yyyy-MM-dd";

/**
 * The currency a figure group is expressed in.
 */
type FigureCurrency = {
    id: string;
    code: string;
    decimal_places: number;
    symbol: string;
};

/**
 * How a total moved since the month before, or null
 * when there is no month to compare against.
 */
type Delta = {
    amount: string;
    percentage: number | null;
};

/**
 * A total of the month next to its delta.
 */
type Total = {
    amount: string;
    delta: Delta | null;
};

/**
 * A row of the tag breakdown, where a null tag is the
 * money that carries no tag at all.
 */
type TagRow = {
    budget: BudgetProgressFigures | null;
    expenses: string;
    id: string | null;
    income: string;
    name: string | null;
    net: string;
};

/**
 * One month of the trend series.
 */
type TrendPoint = {
    expenses: string;
    income: string;
    month: string;
    net: string;
};

/**
 * Everything the page shows for a single currency.
 */
export type Figure = {
    currency: FigureCurrency;
    expenses: Total;
    income: Total;
    net: Total;
    tags: Array<TagRow>;
    transfers: { moved_in: string; moved_out: string } | null;
    trend: Array<TrendPoint>;
};

type PageProps = {
    can_budget: boolean;
    earliest_month: string;
    figures: Array<Figure>;
    has_accounts: boolean;
    is_locked: boolean;
    latest_month: string;
    month: string;
    team: Team;
};

/**
 * Read a yyyy-MM string as the first day of that month.
 */
const toMonth = (month: string): Date => startOfMonth(parse(month, MONTH_FORMAT, new Date()));

/**
 * Whether the currency saw anything at all in the month,
 * which is what decides if a chart has something to draw.
 */
const hasActivity = (figure: Figure): boolean =>
    Number(figure.income.amount) !== 0 || Number(figure.expenses.amount) !== 0 || figure.transfers !== null;

/**
 * The colour of the nth donut slice. Hues step by the golden
 * angle so neighbours never look alike however many tags a
 * month carries, at a lightness that reads on either theme.
 */
const sliceColor = (index: number): string => `oklch(0.68 0.14 ${(index * 137.508) % 360})`;

/**
 * The transactions index filter that narrows the listing to
 * the same month, currency, type and tag a figure covers.
 */
const monthFilter = (
    month: string,
    currency: string,
    type?: "income" | "expense" | "transfer",
    tag?: string | null,
): Record<string, string> => {
    const selected = toMonth(month);

    return {
        currency,
        from: format(selected, DATE_FORMAT),
        to: format(endOfMonth(selected), DATE_FORMAT),
        ...(type ? { type } : {}),
        ...(tag ? { tags: tag } : {}),
    };
};

const Monthly = () => {
    /**
     * The page's props.
     */
    const { has_accounts, is_locked } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {!has_accounts ? (
                <EmptyAccounts />
            ) : is_locked ? (
                <LockedMonth />
            ) : (
                <Deferred data="figures" fallback={<SkeletonFigures />}>
                    <Figures />
                </Deferred>
            )}
        </div>
    );
};

const Header = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { has_accounts, month, team } = usePage<PageProps>().props;

    return (
        <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
                <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{team.name}</h1>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("How :name went.", { name: formatMonth(toMonth(month), currentLocale()) })}
                </p>
            </div>

            {/** A team without accounts has no month to step through. */}
            {has_accounts && <MonthNavigation />}
        </header>
    );
};

const MonthNavigation = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { earliest_month, latest_month, month, team } = usePage<PageProps>().props;

    /**
     * Whether the month picker is open.
     */
    const [isOpen, setIsOpen] = useState(false);

    const selected = toMonth(month);
    const earliest = toMonth(earliest_month);
    const latest = toMonth(latest_month);

    const previous = subMonths(selected, 1);
    const next = addMonths(selected, 1);

    /**
     * Visit the given month, leaving the parameter out for
     * the current one so the canonical URL stays clean.
     */
    const visit = (target: Date) => {
        setIsOpen(false);

        router.visit(
            monthly.index.url(
                { team: team.slug },
                { query: format(target, MONTH_FORMAT) === latest_month ? {} : { month: format(target, MONTH_FORMAT) } },
            ),
        );
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                disabled={isBefore(previous, earliest)}
                onClick={() => visit(previous)}
                size="icon"
                variant="outline"
            >
                <ChevronLeftIcon />
                <span className="sr-only">{t("Previous month")}</span>
            </Button>

            <Popover onOpenChange={setIsOpen} open={isOpen}>
                <PopoverTrigger
                    render={
                        <Button variant="outline">
                            <CalendarIcon data-icon="inline-start" />
                            {formatMonth(selected, currentLocale())}
                        </Button>
                    }
                />
                <PopoverContent align="end" className="w-auto p-0">
                    <Calendar
                        captionLayout="dropdown"
                        defaultMonth={selected}
                        disabled={{ after: endOfMonth(latest), before: earliest }}
                        endMonth={endOfMonth(latest)}
                        mode="single"
                        onSelect={(day) => day && visit(startOfMonth(day))}
                        selected={selected}
                        startMonth={earliest}
                    />
                </PopoverContent>
            </Popover>

            <Button disabled={isAfter(next, latest)} onClick={() => visit(next)} size="icon" variant="outline">
                <ChevronRightIcon />
                <span className="sr-only">{t("Next month")}</span>
            </Button>
        </div>
    );
};

const Figures = () => {
    /**
     * The page's props.
     */
    const { figures } = usePage<PageProps>().props;

    if (!figures.length) {
        return <EmptyTransactions />;
    }

    return (
        <div className="flex flex-col gap-12">
            {figures.map((figure) => (
                <CurrencySection figure={figure} key={figure.currency.id} />
            ))}
        </div>
    );
};

const CurrencySection = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <section className="flex flex-col gap-4">
            <h2 className="font-heading text-lg font-medium">{figure.currency.code}</h2>

            {/** Income, expenses and net */}
            <Totals figure={figure} />

            {/** The trend behind the delta and the share of each tag */}
            {hasActivity(figure) && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <TrendChart figure={figure} />
                    <ExpensesDonut figure={figure} />
                </div>
            )}

            {/** Where the money went */}
            {figure.tags.length ? (
                <div className="flex flex-col gap-2">
                    <BudgetsUpsell />
                    <TagTable figure={figure} />
                    <p className="text-muted-foreground text-xs">
                        {t(
                            "A transaction counts under each of its tags, so these rows can add up to more than the totals.",
                        )}
                    </p>
                </div>
            ) : (
                <EmptyTransactions />
            )}

            {/** What moved between the team's own accounts */}
            {figure.transfers && <Transfers figure={figure} />}
        </section>
    );
};

const Totals = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="min-w-0 overflow-x-auto rounded-xl border">
            <div className="flex min-w-full divide-x">
                <TotalCell
                    className="text-success"
                    currency={figure.currency}
                    isGood={(delta) => Number(delta) > 0}
                    label={t("Income")}
                    total={figure.income}
                    type="income"
                />
                <TotalCell
                    className="text-destructive"
                    currency={figure.currency}
                    isGood={(delta) => Number(delta) < 0}
                    label={t("Expenses")}
                    total={figure.expenses}
                    type="expense"
                />
                <TotalCell
                    currency={figure.currency}
                    isGood={(delta) => Number(delta) > 0}
                    label={t("Net")}
                    total={figure.net}
                />
            </div>
        </div>
    );
};

const TotalCell = ({
    className,
    currency,
    isGood,
    label,
    total,
    type,
}: {
    className?: string;
    currency: FigureCurrency;
    isGood: (delta: string) => boolean;
    label: string;
    total: Total;
    type?: "income" | "expense";
}) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * The page's props.
     */
    const { month, team } = usePage<PageProps>().props;

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    const isNegative = total.amount.startsWith("-");

    return (
        <Link
            className="hover:bg-muted flex min-w-40 flex-auto flex-col gap-1 p-4"
            href={transactions.index.url(
                { team: team.slug },
                { query: { filter: monthFilter(month, currency.id, type) } },
            )}
            prefetch
        >
            <span className="text-muted-foreground text-sm">{label}</span>
            <span
                className={cn(
                    "font-heading text-xl font-semibold whitespace-nowrap tabular-nums sm:text-2xl",
                    className,
                    !className && isNegative && "text-destructive",
                )}
            >
                {mask(`${isNegative ? "- " : ""}${formatAmount(total.amount, currentLocale(), currency)}`)}
            </span>
            <DeltaLabel currency={currency} delta={total.delta} isGood={isGood} />
        </Link>
    );
};

const DeltaLabel = ({
    currency,
    delta,
    isGood,
}: {
    currency: FigureCurrency;
    delta: Delta | null;
    isGood: (delta: string) => boolean;
}) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    if (!delta || delta.percentage === null) {
        return (
            <span className="text-muted-foreground text-xs" title={t("vs previous month")}>
                {"—"}
            </span>
        );
    }

    const isUp = Number(delta.amount) > 0;
    const unsigned = delta.amount.startsWith("-") ? delta.amount.slice(1) : delta.amount;

    return (
        <span
            className={cn("text-xs tabular-nums", isGood(delta.amount) ? "text-success" : "text-destructive")}
            title={t("vs previous month")}
        >
            {`${isUp ? "+" : "-"} ${mask(formatAmount(unsigned, currentLocale(), currency))} (${formatPercentage(Math.abs(delta.percentage), currentLocale())})`}
        </span>
    );
};

const BudgetsUpsell = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { can_budget, team } = usePage<PageProps>().props;

    /**
     * A team whose plan already has budgets has nothing
     * to learn here.
     */
    if (can_budget) {
        return null;
    }

    return (
        <Item variant="muted">
            <ItemMedia variant="icon">
                <WalletIcon className="text-muted-foreground" />
            </ItemMedia>
            <ItemContent>
                <ItemDescription>
                    {t("Set budgets by category or tag with Pro and follow every area against its limit.")}
                </ItemDescription>
            </ItemContent>
            <ItemActions>
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink
                            href={subscriptions.upgrade.url(
                                { team: team.slug },
                                { query: { feature: "budgets:manage" } },
                            )}
                        >
                            {t("Upgrade to Pro")}
                        </ModalLink>
                    }
                    size="sm"
                    variant="outline"
                />
            </ItemActions>
        </Item>
    );
};

const TagTable = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { month, team } = usePage<PageProps>().props;

    return (
        <div className="min-w-0 overflow-x-auto rounded-xl border">
            <TableComponent>
                <TableHeader>
                    <TableRow>
                        <TableHead>{t("Tag")}</TableHead>
                        <TableHead className="text-right whitespace-nowrap">{t("Income")}</TableHead>
                        <TableHead className="text-right whitespace-nowrap">{t("Expenses")}</TableHead>
                        <TableHead className="text-right whitespace-nowrap">{t("Net")}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {figure.tags.map((tag) => (
                        <TableRow key={tag.id ?? "untagged"}>
                            <TableCell>
                                <Link
                                    className="hover:underline"
                                    href={transactions.index.url(
                                        { team: team.slug },
                                        {
                                            query: {
                                                filter: monthFilter(month, figure.currency.id, undefined, tag.id),
                                            },
                                        },
                                    )}
                                    prefetch
                                >
                                    <span className={cn("block truncate", !tag.name && "text-muted-foreground")}>
                                        {tag.name ?? t("Untagged")}
                                    </span>
                                </Link>
                                {/** A budgeted tag shows how far the month has
                                 * gone into it, right under its name. */}
                                {tag.budget ? (
                                    <BudgetProgress
                                        className="mt-1.5 max-w-xs"
                                        currency={figure.currency}
                                        progress={tag.budget}
                                    />
                                ) : null}
                            </TableCell>
                            <TagAmount amount={tag.income} className="text-success" currency={figure.currency} />
                            <TagAmount amount={tag.expenses} className="text-destructive" currency={figure.currency} />
                            <TagAmount amount={tag.net} currency={figure.currency} signed />
                        </TableRow>
                    ))}
                </TableBody>
            </TableComponent>
        </div>
    );
};

const TagAmount = ({
    amount,
    className,
    currency,
    signed = false,
}: {
    amount: string;
    className?: string;
    currency: FigureCurrency;
    signed?: boolean;
}) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    const isZero = Number(amount) === 0;
    const isNegative = amount.startsWith("-");

    /**
     * The currency is already named by the section heading, so
     * the cells carry the number alone and the column stays
     * narrow enough to read.
     */
    const formatted = formatAmount(isNegative ? amount.slice(1) : amount, currentLocale(), currency, {
        symbol: false,
    });

    return (
        <TableCell
            className={cn(
                "text-right font-medium whitespace-nowrap tabular-nums",
                isZero ? "text-muted-foreground/60 font-normal" : (className ?? (isNegative && "text-destructive")),
            )}
        >
            {/** A column of zeroes reads as noise, so nothing is a dash. */}
            {isZero ? "—" : mask(`${signed && isNegative ? "- " : ""}${formatted}`)}
        </TableCell>
    );
};

const Transfers = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { month, team } = usePage<PageProps>().props;

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    if (!figure.transfers) {
        return null;
    }

    return (
        <div className="min-w-0 overflow-x-auto rounded-xl border">
            <div className="flex min-w-full divide-x">
                {(
                    [
                        ["moved_out", t("Moved out")],
                        ["moved_in", t("Moved in")],
                    ] as const
                ).map(([key, label]) => (
                    <Link
                        className="hover:bg-muted flex min-w-40 flex-auto flex-col gap-1 p-4"
                        href={transactions.index.url(
                            { team: team.slug },
                            { query: { filter: monthFilter(month, figure.currency.id, "transfer") } },
                        )}
                        key={key}
                        prefetch
                    >
                        <span className="text-muted-foreground text-sm">{label}</span>
                        <span className="text-lg font-medium whitespace-nowrap tabular-nums">
                            {mask(formatAmount(figure.transfers?.[key] ?? "0", currentLocale(), figure.currency))}
                        </span>
                    </Link>
                ))}
            </div>
        </div>
    );
};

const TrendChart = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    /**
     * How each series of the chart is named and coloured.
     */
    const config = {
        expenses: { label: t("Expenses"), color: "var(--destructive)" },
        income: { label: t("Income"), color: "var(--success)" },
        net: { label: t("Net"), color: "var(--muted-foreground)" },
    } satisfies ChartConfig;

    /**
     * The series as numbers, since the figures travel as
     * decimal strings and charts plot numbers.
     */
    const data = figure.trend.map((point) => ({
        expenses: Number(point.expenses),
        income: Number(point.income),
        label: formatMonth(toMonth(point.month), currentLocale(), { month: "short", year: undefined }),
        net: Number(point.net),
    }));

    return (
        <figure
            aria-label={t("Income vs expenses")}
            className="flex min-w-0 flex-col gap-2 rounded-xl border p-4"
            role="group"
        >
            <figcaption className="text-muted-foreground text-sm">{t("Income vs expenses")}</figcaption>
            <ChartContainer className="aspect-video w-full" config={config}>
                <ComposedChart accessibilityLayer data={data}>
                    <CartesianGrid vertical={false} />
                    <XAxis axisLine={false} dataKey="label" tickLine={false} tickMargin={8} />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                formatter={(value, name) => (
                                    <div className="flex w-full justify-between gap-4">
                                        <span className="text-muted-foreground">
                                            {config[name as keyof typeof config]?.label ?? name}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {mask(formatAmount(String(value), currentLocale(), figure.currency))}
                                        </span>
                                    </div>
                                )}
                            />
                        }
                    />
                    {/** The series are drawn at their value: a growth animation on a
                     * figure the reader came here to read buys nothing, and it leaves
                     * the chart blank whenever the frames are throttled. */}
                    <Bar dataKey="income" fill="var(--color-income)" isAnimationActive={false} radius={4} />
                    <Bar dataKey="expenses" fill="var(--color-expenses)" isAnimationActive={false} radius={4} />
                    <Line
                        dataKey="net"
                        dot={false}
                        isAnimationActive={false}
                        stroke="var(--color-net)"
                        strokeWidth={2}
                        type="monotone"
                    />
                    <ChartLegend content={<ChartLegendContent className="flex-wrap" />} />
                </ComposedChart>
            </ChartContainer>
        </figure>
    );
};

const ExpensesDonut = ({ figure }: { figure: Figure }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Mask the figures while balances are hidden.
     */
    const mask = useBalances().mask;

    /**
     * The tags that spent something, since a zero slice
     * draws nothing and clutters the legend. Untagged money
     * keeps the muted colour it has in the table.
     */
    const slices = figure.tags
        .filter((tag) => Number(tag.expenses) > 0)
        .map((tag, index) => ({
            color: tag.id === null ? "var(--muted-foreground)" : sliceColor(index),
            expenses: Number(tag.expenses),
            name: tag.name ?? t("Untagged"),
        }));

    if (!slices.length) {
        return null;
    }

    return (
        <figure
            aria-label={t("Expenses by tag")}
            className="flex min-w-0 flex-col gap-2 rounded-xl border p-4"
            role="group"
        >
            <figcaption className="text-muted-foreground text-sm">{t("Expenses by tag")}</figcaption>
            <ChartContainer className="aspect-video w-full" config={{}}>
                <PieChart>
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                formatter={(value, name) => (
                                    <div className="flex w-full justify-between gap-4">
                                        <span className="text-muted-foreground">{name}</span>
                                        <span className="font-medium tabular-nums">
                                            {mask(formatAmount(String(value), currentLocale(), figure.currency))}
                                        </span>
                                    </div>
                                )}
                                hideLabel
                            />
                        }
                    />
                    <Pie
                        data={slices}
                        dataKey="expenses"
                        innerRadius="55%"
                        isAnimationActive={false}
                        nameKey="name"
                        strokeWidth={2}
                    >
                        {slices.map((slice) => (
                            <Cell fill={slice.color} key={slice.name} />
                        ))}
                    </Pie>
                </PieChart>
            </ChartContainer>

            {/** The legend lives outside the chart so a long tag
             * list wraps instead of squeezing the donut. */}
            <ul className="flex flex-wrap gap-x-3 gap-y-1.5">
                {slices.map((slice) => (
                    <li className="text-muted-foreground flex min-w-0 items-center gap-1.5 text-xs" key={slice.name}>
                        <span className="size-2 shrink-0 rounded-[2px]" style={{ backgroundColor: slice.color }} />
                        <span className="truncate">{slice.name}</span>
                    </li>
                ))}
            </ul>

            <p className="text-muted-foreground text-xs">{t("Share of tagged spending")}</p>
        </figure>
    );
};

const LockedMonth = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return (
        <div className="overflow-hidden rounded-xl border">
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <LockIcon />
                    </EmptyMedia>
                    <EmptyTitle>{t("This month is not in your plan")}</EmptyTitle>
                    <EmptyDescription>
                        {t("The free plan shows the last six months. Upgrade to Pro to open your whole history.")}
                    </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <Button
                        nativeButton={false}
                        render={<Link href={subscriptions.index.url({ team: team.slug })}>{t("Upgrade to Pro")}</Link>}
                    />
                </EmptyContent>
            </Empty>
        </div>
    );
};

const EmptyAccounts = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return (
        <div className="overflow-hidden rounded-xl border">
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <CircleDashedIcon />
                    </EmptyMedia>
                    <EmptyTitle>{t("No :name yet", { name: t("accounts") })}</EmptyTitle>
                    <EmptyDescription>{t("Add :name to get started.", { name: t("accounts") })}</EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <Button
                        nativeButton={false}
                        render={
                            <ModalLink href={accounts.create.url({ team: team.slug })}>
                                <PlusIcon data-icon="inline-start" />
                                {t("Create :name", { name: t("account") })}
                            </ModalLink>
                        }
                    />
                </EmptyContent>
            </Empty>
        </div>
    );
};

const EmptyTransactions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { team } = usePage<PageProps>().props;

    return (
        <div className="overflow-hidden rounded-xl border">
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <CircleDashedIcon />
                    </EmptyMedia>
                    <EmptyTitle>{t("Nothing happened this month")}</EmptyTitle>
                    <EmptyDescription>{t("Add :name to get started.", { name: t("transactions") })}</EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <div className="flex flex-wrap justify-center gap-2">
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={transactions.create.url({ team: team.slug, type: "income" })}>
                                    <ArrowDownIcon data-icon="inline-start" />
                                    {t("Register an :name", { name: t("income") })}
                                </ModalLink>
                            }
                            variant="outline"
                        />
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={transactions.create.url({ team: team.slug, type: "expense" })}>
                                    <ArrowUpIcon data-icon="inline-start" />
                                    {t("Register an :name", { name: t("expense") })}
                                </ModalLink>
                            }
                        />
                    </div>
                </EmptyContent>
            </Empty>
        </div>
    );
};

const SkeletonFigures = () => {
    return (
        <div className="flex flex-col gap-12">
            {[0, 1].map((section) => (
                <div className="flex flex-col gap-4" key={section}>
                    <Skeleton className="h-6 w-16" />
                    <div className="overflow-hidden rounded-xl border">
                        <div className="flex divide-x">
                            {[0, 1, 2].map((cell) => (
                                <div className="flex flex-1 flex-col gap-2 p-4" key={cell}>
                                    <Skeleton className="h-4 w-16" />
                                    <Skeleton className="h-7 w-28" />
                                    <Skeleton className="h-3 w-20" />
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="overflow-hidden rounded-xl border">
                        <div className="flex flex-col gap-3 p-4">
                            {[0, 1, 2].map((row) => (
                                <div className="flex items-center justify-between gap-4" key={row}>
                                    <Skeleton className="h-4 w-32" />
                                    <Skeleton className="h-4 w-20" />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
};

Monthly.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Monthly;
