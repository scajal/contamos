import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CircleDashedIcon,
    EyeIcon,
    MoreHorizontalIcon,
    PauseIcon,
    PencilIcon,
    PlayIcon,
    PlusIcon,
    RepeatIcon,
    TrashIcon,
} from "lucide-react";
import { Fragment, useState } from "react";

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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { useBalances } from "@/hooks/use-balances";
import { ApplicationLayout } from "@/layouts/application/layout";
import { scheduleLabel, TRANSACTION_AMOUNT_COLOR, TRANSACTION_AMOUNT_SIGN } from "@/lib/transactions";
import { cn, formatAmount, formatDate } from "@/lib/utils";
import routes from "@/routes/finance/teams/recurring-rules";
import { RecurringRule } from "@/types/app/modules/core/finance/models/team/recurring-rule";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    canRecur: boolean;
    rules: LengthAwarePaginator<RecurringRule>;
};

const Index = () => {
    /**
     * The page's props.
     */
    const { canRecur } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-8">
            <Header />
            {canRecur ? <Listing /> : <LockedRules />}
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
    const { canRecur, user } = usePage<PageProps>().props;

    return (
        <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
                <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                    {t("Recurring")}
                </h1>
                <p className="text-muted-foreground text-[15px] leading-relaxed">
                    {t("Rules that generate a transaction on their own schedule.")}
                </p>
            </div>
            {canRecur && (
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={routes.create.url({ team: user?.current_team?.slug ?? "" })}>
                            <PlusIcon data-icon="inline-start" />
                            {t("New rule")}
                        </ModalLink>
                    }
                />
            )}
        </header>
    );
};

/**
 * What a team on a plan without recurring transactions sees: the rules it
 * already created stay on the page, blurred behind the lock, since nothing
 * is lost — they simply do not fire until the team upgrades.
 */
const LockedRules = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { rules, user } = usePage<PageProps>().props;

    return (
        <Deferred data="rules" fallback={<SkeletonRows />}>
            <FeatureLock
                description={t(
                    "A rule records an income or an expense on its own schedule. The rules you already created are kept.",
                )}
                feature="transactions:recur"
                team={user?.current_team?.slug ?? ""}
                title={t("Recurring transactions are part of Pro")}
            >
                {rules?.data?.length ? <Rows /> : null}
            </FeatureLock>
        </Deferred>
    );
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
    const { rules, user } = usePage<PageProps>().props;

    /**
     * The team the rules belong to.
     */
    const team = user?.current_team?.slug ?? "";

    return (
        <Deferred data="rules" fallback={<SkeletonRows />}>
            {!rules?.data?.length ? (
                <div className="rounded-xl border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <CircleDashedIcon />
                            </EmptyMedia>
                            <EmptyTitle>{t("No :name yet", { name: t("recurring rules") })}</EmptyTitle>
                            <EmptyDescription>
                                {t("Turn on Repeat while recording an income or an expense to create one.")}
                            </EmptyDescription>
                        </EmptyHeader>
                        <EmptyContent>
                            <Button
                                nativeButton={false}
                                render={<ModalLink href={routes.create.url({ team })}>{t("New rule")}</ModalLink>}
                                variant="outline"
                            />
                        </EmptyContent>
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border">
                        <Rows />
                    </div>
                    <PaginationControls nextUrl={rules?.next_page_url} previousUrl={rules?.prev_page_url} />
                </div>
            )}
        </Deferred>
    );
};

/**
 * The rules themselves, shared by the listing and by what a team
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
    const { canRecur, rules, user } = usePage<PageProps>().props;

    /**
     * Mask the amounts while balances are hidden.
     */
    const mask = useBalances().mask;

    /**
     * The team the rules belong to.
     */
    const team = user?.current_team?.slug ?? "";

    return (
        <ItemGroup className="gap-0">
            {(rules?.data ?? []).map((rule) => (
                <Item
                    className="rounded-none border-0 border-b last:border-b-0"
                    key={rule.id}
                    size="sm"
                    variant="outline"
                >
                    <ItemMedia variant="icon">
                        <RepeatIcon />
                    </ItemMedia>
                    <ItemContent>
                        <ItemTitle className="flex flex-wrap items-center gap-2">
                            {rule.description || t(`enums.${rule.type}`)}
                            <StatusBadge rule={rule} />
                        </ItemTitle>
                        <ItemDescription>
                            {[
                                rule.account?.name,
                                scheduleLabel(rule, currentLocale(), t),
                                rule.next_due_date
                                    ? t("Next :date", {
                                          date: formatDate(rule.next_due_date, currentLocale()),
                                      })
                                    : t("Ended"),
                                rule.last_generated_due_date
                                    ? t("Last :date", {
                                          date: formatDate(rule.last_generated_due_date, currentLocale()),
                                      })
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(" · ")}
                        </ItemDescription>
                    </ItemContent>
                    <ItemActions className="flex-wrap justify-end gap-3">
                        <span className={cn("font-medium tabular-nums", TRANSACTION_AMOUNT_COLOR[rule.type])}>
                            {mask(
                                `${TRANSACTION_AMOUNT_SIGN[rule.type]} ${formatAmount(rule.amount, currentLocale(), rule.currency)}`,
                            )}
                        </span>
                        <RuleActions canRecur={canRecur} rule={rule} team={team} />
                    </ItemActions>
                </Item>
            ))}
        </ItemGroup>
    );
};

/**
 * The View, Edit, Pause or Resume, and Delete actions of a rule. A
 * team whose plan cannot recur only gets to look and to delete.
 */
const RuleActions = ({ canRecur, rule, team }: { canRecur: boolean; rule: RecurringRule; team: string }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * Whether the delete confirmation dialog is open.
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
                                <ModalLink href={routes.show.url({ recurringRule: rule.id, team })}>
                                    <EyeIcon />
                                    <span>{t("View")}</span>
                                </ModalLink>
                            }
                        />
                        {canRecur && (
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={routes.edit.url({ recurringRule: rule.id, team })}>
                                        <PencilIcon />
                                        <span>{t("Edit")}</span>
                                    </ModalLink>
                                }
                            />
                        )}
                    </DropdownMenuGroup>
                    {canRecur && (
                        <Fragment>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    render={
                                        <Link
                                            as="button"
                                            className="w-full"
                                            href={
                                                rule.is_active
                                                    ? routes.pause.url({ recurringRule: rule.id, team })
                                                    : routes.resume.url({ recurringRule: rule.id, team })
                                            }
                                            method="patch"
                                            preserveScroll
                                        >
                                            {rule.is_active ? <PauseIcon /> : <PlayIcon />}
                                            <span>{rule.is_active ? t("Pause") : t("Resume")}</span>
                                        </Link>
                                    }
                                />
                            </DropdownMenuGroup>
                        </Fragment>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuItem onClick={() => setDeleteOpen(true)} variant="destructive">
                            <TrashIcon />
                            <span>{t("Delete")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmAction
                action={
                    <Link
                        as="button"
                        href={routes.destroy.url({ recurringRule: rule.id, team })}
                        method="delete"
                        preserveScroll
                    >
                        {t("Delete")}
                    </Link>
                }
                confirmLabel={t("Delete")}
                description={t("The transactions it already created stay in your ledger. It will not create any more.")}
                onOpenChange={setDeleteOpen}
                open={deleteOpen}
                title={t("Delete this rule?")}
            />
        </Fragment>
    );
};

/**
 * Whether the rule runs, is suspended, or could not generate its last
 * occurrence, with the reason it failed.
 */
const StatusBadge = ({ rule }: { rule: RecurringRule }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    if (rule.status === "active") {
        return null;
    }

    return (
        <Badge variant={rule.status === "failed" ? "destructive" : "secondary"}>
            {rule.status === "failed" ? (rule.failure_reason ?? t("Failed")) : t("Paused")}
        </Badge>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-xl border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-xl" />
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-24" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
