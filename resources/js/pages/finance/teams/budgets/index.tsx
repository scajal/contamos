import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { parse } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CircleDashedIcon,
    MoreHorizontalIcon,
    PauseIcon,
    PencilIcon,
    PlayIcon,
    PlusIcon,
    TagIcon,
    TrashIcon,
} from "lucide-react";
import { Fragment, useState } from "react";

import { BudgetProgress } from "@/components/budget-progress";
import { CategoryIcon } from "@/components/category-icon";
import { ConfirmAction } from "@/components/confirm-action";
import { FeatureLock } from "@/components/feature-lock";
import { PaginationControls } from "@/components/pagination-controls";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { useBalances } from "@/hooks/use-balances";
import { ApplicationLayout } from "@/layouts/application/layout";
import { formatAmount, formatMonth } from "@/lib/utils";
import routes from "@/routes/finance/teams/budgets";
import { BudgetPeriodStatus } from "@/types/app/modules/core/finance/enums/budget-period/budget-period-status";
import { BudgetRow } from "@/types/app/modules/core/finance/models/team/budget-period";
import { Team } from "@/types/app/modules/core/identity/models/team";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

/**
 * The month format the budgets travel in.
 */
const MONTH_FORMAT = "yyyy-MM";

type PageProps = {
    budgets: LengthAwarePaginator<BudgetRow>;
    is_locked: boolean;
    team: Team;
};

/**
 * Read a yyyy-MM key as a local date, since a date-only string
 * is UTC midnight and would show the previous month west of
 * UTC.
 */
const toMonth = (value: string): Date => parse(value, MONTH_FORMAT, new Date());

const Index = () => {
    /**
     * The page's props.
     */
    const { is_locked } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {is_locked ? <LockedBudgets /> : <Listing />}
        </div>
    );
};

const Header = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { is_locked } = usePage<PageProps>().props;

    return (
        <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
                <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                    {t("Budgets")}
                </h1>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("How much this team means to spend on each category or tag, month by month.")}
                </p>
            </div>
            {!is_locked && <CreateBudgetButton />}
        </header>
    );
};

const CreateBudgetButton = ({ className }: { className?: string }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    return (
        <Button
            className={className}
            nativeButton={false}
            render={
                <ModalLink href={routes.create.url({ team: user?.current_team?.slug ?? "" })}>
                    <PlusIcon data-icon="inline-start" />
                    {t("Create :name", { name: t("budget") })}
                </ModalLink>
            }
        />
    );
};

const StatusBadge = ({ status }: { status: BudgetPeriodStatus }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    const labels: Record<BudgetPeriodStatus, string> = {
        ended: t("Ended"),
        paused: t("Paused"),
        running: t("Running"),
        scheduled: t("Scheduled"),
    };

    return <Badge variant={status === "running" ? "default" : "secondary"}>{labels[status]}</Badge>;
};

const Months = ({ budget }: { budget: BudgetRow }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    const from = formatMonth(toMonth(budget.from_month), currentLocale(), { month: "short", year: "numeric" });

    // A stretch with no end runs until somebody stops it.
    if (!budget.until_month) {
        return <span>{t("From :month", { month: from })}</span>;
    }

    const until = formatMonth(toMonth(budget.until_month), currentLocale(), { month: "short", year: "numeric" });

    // A one-month objective names its single month.
    return <span>{from === until ? from : `${from} – ${until}`}</span>;
};

const Listing = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { budgets } = usePage<PageProps>().props;

    return (
        <Deferred data="budgets" fallback={<SkeletonRows />}>
            {!budgets?.data?.length ? (
                <div className="rounded-xl border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CircleDashedIcon />
                            </EmptyMedia>
                            <EmptyTitle>{t("No :name yet", { name: t("budgets") })}</EmptyTitle>
                            <EmptyDescription>
                                {t("Add :name to get started.", { name: t("budgets") })}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <CreateBudgetButton />
                        </EmptyContent>
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border">
                        <Rows />
                    </div>
                    <PaginationControls nextUrl={budgets?.next_page_url} previousUrl={budgets?.prev_page_url} />
                </div>
            )}
        </Deferred>
    );
};

/**
 * The budgets themselves, shared by the listing and by what a team
 * without the feature sees behind the lock.
 */
const Rows = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { budgets, is_locked, user } = usePage<PageProps>().props;

    /**
     * Mask the amounts while balances are hidden.
     */
    const mask = useBalances().mask;

    /**
     * The team the budgets belong to.
     */
    const team = user?.current_team?.slug ?? "";

    return (
        <ItemGroup className="gap-0">
            {(budgets?.data ?? []).map((budget) => (
                <Item
                    className="items-start rounded-none border-0 border-b last:border-b-0"
                    key={budget.id}
                    size="sm"
                    variant="outline"
                >
                    <ItemMedia variant="icon">
                        {budget.target.kind === "category" ? (
                            <CategoryIcon category={budget.target.category.value} />
                        ) : (
                            <TagIcon />
                        )}
                    </ItemMedia>
                    <ItemContent className="min-w-0 gap-2">
                        <ItemTitle className="flex flex-wrap items-center gap-2">
                            <span className="truncate">
                                {budget.target.kind === "category"
                                    ? budget.target.category.label
                                    : budget.target.tag.name}
                            </span>
                            {/** Active is the normal state, so only the exception is labelled. */}
                            {budget.target.kind === "tag" && !budget.target.tag.is_active ? (
                                <Badge variant="secondary">{t("Inactive")}</Badge>
                            ) : null}
                            <StatusBadge status={budget.status} />
                        </ItemTitle>
                        <ItemDescription className="flex flex-wrap items-center gap-x-2 tabular-nums">
                            <span className="font-medium">
                                {mask(formatAmount(budget.amount, currentLocale(), budget.currency))}
                            </span>
                            <span>{budget.currency.code}</span>
                            <span aria-hidden>·</span>
                            <Months budget={budget} />
                        </ItemDescription>
                        {budget.progress ? (
                            <BudgetProgress
                                className="max-w-sm"
                                currency={budget.currency}
                                progress={budget.progress}
                            />
                        ) : null}
                    </ItemContent>
                    {is_locked ? null : (
                        <ItemActions className="flex-wrap justify-end">
                            <ActionsMenu budget={budget} team={team} />
                        </ItemActions>
                    )}
                </Item>
            ))}
        </ItemGroup>
    );
};

const ActionsMenu = ({ budget, team }: { budget: BudgetRow; team: string }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the confirmation dialog is open, kept apart from
     * the menu so closing the menu does not close it.
     */
    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <Fragment>
            <DropdownMenu>
                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                    <MoreHorizontalIcon />
                    <span className="sr-only">{t("Actions")}</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            render={
                                <ModalLink href={routes.edit.url({ budget: budget.id, team })}>
                                    <PencilIcon />
                                    <span>{t("Edit")}</span>
                                </ModalLink>
                            }
                        />
                        {/** Only an open-ended stretch in force can be stopped. */}
                        {budget.status === "running" && !budget.until_month ? (
                            <DropdownMenuItem
                                render={
                                    <Link href={routes.pause.url({ budget: budget.id, team })} method="patch">
                                        <PauseIcon />
                                        <span>{t("Pause")}</span>
                                    </Link>
                                }
                            />
                        ) : null}
                        {/** Only a stretch nothing followed can be picked back up. */}
                        {budget.status === "paused" ? (
                            <DropdownMenuItem
                                render={
                                    <Link href={routes.resume.url({ budget: budget.id, team })} method="patch">
                                        <PlayIcon />
                                        <span>{t("Resume")}</span>
                                    </Link>
                                }
                            />
                        ) : null}
                        <DropdownMenuItem onClick={() => setDeleteOpen(true)} variant="destructive">
                            <TrashIcon />
                            <span>{t("Delete")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmAction
                action={<Link href={routes.destroy.url({ budget: budget.id, team })} method="delete" />}
                confirmLabel={t("Delete")}
                description={t("The months it covered will have no budget. This action cannot be undone.")}
                onOpenChange={setDeleteOpen}
                open={deleteOpen}
                title={t("Are you sure you want to delete the budget?")}
            />
        </Fragment>
    );
};

/**
 * What a team on a plan without budgets sees: the budgets it already
 * recorded stay on the page, blurred behind the lock, since nothing is
 * lost — they simply cannot be followed or changed until the team
 * upgrades.
 */
const LockedBudgets = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { budgets, team } = usePage<PageProps>().props;

    return (
        <Deferred data="budgets" fallback={<SkeletonRows />}>
            <FeatureLock
                description={t(
                    "Set a monthly amount per category or tag and follow it through the month. Any budget you already set is kept.",
                )}
                feature="budgets:manage"
                team={team.slug}
                title={t("Budgets are part of Pro")}
            >
                {budgets?.data?.length ? <Rows /> : null}
            </FeatureLock>
        </Deferred>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-start gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-xl" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-56" />
                        <Skeleton className="h-1.5 w-full max-w-sm" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
