import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format, parseISO } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowDownIcon,
    ArrowUpIcon,
    CalendarRangeIcon,
    CheckIcon,
    ChevronDownIcon,
    CircleDashedIcon,
    EyeIcon,
    PlusIcon,
} from "lucide-react";
import { useState } from "react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemGroup,
    ItemHeader,
    ItemMedia,
    ItemTitle,
} from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { usePermissions } from "@/hooks/use-permissions";
import { ApplicationLayout } from "@/layouts/application/layout";
import { PERMISSIONS } from "@/lib/permissions";
import {
    TRANSACTION_AMOUNT_COLOR,
    TRANSACTION_AMOUNT_SIGN,
    TRANSACTION_COLUMNS,
    TRANSACTION_TABLE_CLASS,
} from "@/lib/transactions";
import { cn, formatAmount } from "@/lib/utils";
import accounts from "@/routes/finance/teams/accounts";
import monthly from "@/routes/finance/teams/monthly";
import transactions from "@/routes/finance/teams/transactions";
import invitations from "@/routes/identity/teams/invitations";
import { AccountBalance } from "@/types/app/modules/core/finance/models/team/account-balance";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    balances: Array<AccountBalance>;
    has_accounts: boolean;
    onboarding: Onboarding;
    recent_transactions: Array<Transaction>;
    team: Team;
};

/**
 * Which of the team's setup steps are done, shared by
 * App\Modules\Core\Finance\Http\Controllers\Team\DashboardController.
 */
type Onboarding = {
    has_account: boolean;
    has_member: boolean;
    has_transaction: boolean;
};

/**
 * The storage key holding the dismissal of a team's setup
 * checklist, kept per team so dismissing one team's checklist
 * leaves the others alone.
 */
const dismissalKey = (team: string) => `onboarding-dismissed:${team}`;

/**
 * Read whether the checklist was dismissed for the team in this
 * browser. Private windows and blocked site data throw on access,
 * where nothing was ever dismissed.
 */
const readDismissal = (team: string): boolean => {
    try {
        return window.localStorage.getItem(dismissalKey(team)) === "1";
    } catch {
        return false;
    }
};

/**
 * Remember that the checklist was dismissed for the team. A
 * browser that refuses to store it hides the checklist for this
 * visit alone.
 */
const writeDismissal = (team: string): void => {
    try {
        window.localStorage.setItem(dismissalKey(team), "1");
    } catch {
        return;
    }
};

const Dashboard = () => {
    /**
     * The page's props.
     */
    const { has_accounts, onboarding, team } = usePage<PageProps>().props;

    /**
     * Whether the team finished setting itself up, which retires
     * the checklist for everyone.
     */
    const isSetUp = onboarding.has_account && onboarding.has_transaction && onboarding.has_member;

    /**
     * Whether the checklist was dismissed, remembered against the
     * team it was read for so switching teams reads again.
     */
    const [dismissal, setDismissal] = useState(() => ({ dismissed: readDismissal(team.id), team: team.id }));

    // Visiting another team's dashboard keeps this component
    // mounted, so the dismissal is re-read during the render
    // that brings the new team in.
    if (dismissal.team !== team.id) {
        setDismissal({ dismissed: readDismissal(team.id), team: team.id });
    }

    /**
     * Whether the checklist is on the page.
     */
    const showsChecklist = !isSetUp && !dismissal.dismissed;

    /**
     * Hide the checklist for this team in this browser.
     */
    const dismiss = () => {
        writeDismissal(team.id);

        setDismissal({ dismissed: true, team: team.id });
    };

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Setup checklist */}
            {showsChecklist && <SetupChecklist onDismiss={dismiss} />}

            {has_accounts ? (
                <Fragment>
                    {/** Balances */}
                    <Deferred data="balances" fallback={<SkeletonStrip />}>
                        <Balances />
                    </Deferred>

                    {/** Recent activity */}
                    <Deferred data="recent_transactions" fallback={<SkeletonActivity />}>
                        <RecentActivity />
                    </Deferred>
                </Fragment>
            ) : (
                // The checklist opens with the same account step,
                // so it stands in for the empty state.
                !showsChecklist && <EmptyAccounts />
            )}
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
    const { team } = usePage<PageProps>().props;

    return (
        <header className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl font-medium">{team.name}</h1>
                <p className="text-muted-foreground text-sm">{t("Income and expenses across this team's accounts.")}</p>
            </div>
            <Button
                nativeButton={false}
                render={
                    <Link href={monthly.index.url({ team: team.slug })} prefetch>
                        <CalendarRangeIcon data-icon="inline-start" />
                        {t("This month")}
                    </Link>
                }
                variant="outline"
            />
        </header>
    );
};

const Balances = () => {
    /**
     * The page's props.
     */
    const { balances, team } = usePage<PageProps>().props;

    if (!balances.length) {
        return null;
    }

    return (
        <div className="min-w-0 overflow-x-auto rounded-sm border">
            <div className="flex min-w-full divide-x">
                {balances.map((balance) => (
                    <BalanceCell balance={balance} key={balance.id} team={team.slug} />
                ))}
            </div>
        </div>
    );
};

const BalanceCell = ({ balance, team }: { balance: AccountBalance; team: string }) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * The balance as a string, since some drivers
     * return aggregated decimals as numbers.
     */
    const amount = String(balance.balance);

    /**
     * Whether the balance is negative.
     */
    const isNegative = amount.startsWith("-");

    return (
        <Link
            className="hover:bg-muted flex min-w-40 flex-1 flex-col gap-1 p-4"
            href={transactions.index.url({ team }, { query: { filter: { currency: balance.id } } })}
            prefetch
        >
            <span className="text-muted-foreground text-sm">{balance.code}</span>
            <span className={cn("text-xl font-medium tabular-nums", isNegative && "text-destructive")}>
                {`${isNegative ? "- " : ""}${formatAmount(amount, currentLocale(), balance)}`}
            </span>
        </Link>
    );
};

const RecentActivity = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { recent_transactions, team } = usePage<PageProps>().props;

    if (!recent_transactions.length) {
        return <EmptyTransactions team={team.slug} />;
    }

    /**
     * Grouped transactions by date.
     */
    const grouped = Map.groupBy(recent_transactions, (transaction) =>
        format(new Date(transaction.occurred_at), "yyyy-MM-dd"),
    );

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="text-lg font-medium">{t("Recent activity")}</h2>
                <Button
                    nativeButton={false}
                    render={<Link href={transactions.index.url({ team: team.slug })} prefetch />}
                    size="sm"
                    variant="ghost"
                >
                    {t("View :name", { name: t("transactions") })}
                </Button>
            </div>
            {[...grouped].map(([date, items]) => (
                <div className="flex min-w-0 flex-col" key={date}>
                    <Item className="p-0 pb-4">
                        <ItemHeader>
                            <ItemTitle>{format(parseISO(date), "MMMM d, yyyy")}</ItemTitle>
                        </ItemHeader>
                    </Item>
                    <div className="min-w-0 overflow-x-auto rounded-sm border">
                        <TableComponent className={TRANSACTION_TABLE_CLASS}>
                            <TableBody>
                                {items.map((transaction) => (
                                    <TableRow key={transaction.id}>
                                        <TableCell
                                            className={cn(
                                                "text-muted-foreground tabular-nums",
                                                TRANSACTION_COLUMNS.time,
                                            )}
                                        >
                                            {format(new Date(transaction.occurred_at), "HH:mm")}
                                        </TableCell>
                                        <TableCell className={TRANSACTION_COLUMNS.account}>
                                            <span className="block truncate" title={transaction.account?.name ?? "-"}>
                                                {transaction.account?.name ?? "-"}
                                            </span>
                                        </TableCell>
                                        <TableCell className={TRANSACTION_COLUMNS.description}>
                                            <span
                                                className="block truncate"
                                                title={transaction.description || t(`enums.${transaction.type}`)}
                                            >
                                                {transaction.description || t(`enums.${transaction.type}`)}
                                            </span>
                                        </TableCell>
                                        <TableCell className={cn("text-right", TRANSACTION_COLUMNS.amount)}>
                                            <span
                                                className={cn(
                                                    "font-medium tabular-nums",
                                                    TRANSACTION_AMOUNT_COLOR[transaction.type],
                                                )}
                                            >
                                                {`${TRANSACTION_AMOUNT_SIGN[transaction.type]} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`}
                                            </span>
                                        </TableCell>
                                        <TableCell className={cn("text-right", TRANSACTION_COLUMNS.actions)}>
                                            <Button
                                                nativeButton={false}
                                                render={
                                                    <ModalLink
                                                        href={transactions.show.url({
                                                            team: team.slug,
                                                            transaction: transaction.id,
                                                        })}
                                                    >
                                                        <EyeIcon />
                                                        <span className="sr-only">{t("View")}</span>
                                                    </ModalLink>
                                                }
                                                size="icon"
                                                variant="ghost"
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </TableComponent>
                    </div>
                </div>
            ))}
        </div>
    );
};

const SetupChecklist = ({ onDismiss }: { onDismiss: () => void }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { onboarding, team } = usePage<PageProps>().props;

    /**
     * What the user may do on the team.
     */
    const { can } = usePermissions();

    /**
     * The setup steps, in a fixed order whatever is done, each
     * offering its action while it is still open.
     */
    const steps = [
        {
            action: (
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={accounts.create.url({ team: team.slug })}>
                            <PlusIcon data-icon="inline-start" />
                            {t("Create :name", { name: t("account") })}
                        </ModalLink>
                    }
                    size="sm"
                />
            ),
            description: t("Accounts hold your balances per currency."),
            done: onboarding.has_account,
            key: "account",
            title: t("Create an account"),
        },
        {
            action: (
                <DropdownMenu>
                    {/** An account holds the transaction, so the form cannot be submitted without one. */}
                    <DropdownMenuTrigger render={<Button disabled={!onboarding.has_account} size="sm" />}>
                        {t("New :name", { name: t("transaction") })}
                        <ChevronDownIcon data-icon="inline-end" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={transactions.create.url({ team: team.slug, type: "income" })}>
                                        <ArrowDownIcon />
                                        <span>{t("New :name", { name: t("income") })}</span>
                                    </ModalLink>
                                }
                            />
                            <DropdownMenuItem
                                render={
                                    <ModalLink href={transactions.create.url({ team: team.slug, type: "expense" })}>
                                        <ArrowUpIcon />
                                        <span>{t("New :name", { name: t("expense") })}</span>
                                    </ModalLink>
                                }
                            />
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
            description: t("Record an income or an expense on an account."),
            done: onboarding.has_transaction,
            key: "transaction",
            title: t("Add a first transaction"),
        },
        {
            // The step and its tick belong to the team, but only a
            // member who may invite is offered the action.
            action: can(PERMISSIONS.CreateInvitation) ? (
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink href={invitations.create.url({ team: team.slug })}>
                            <PlusIcon data-icon="inline-start" />
                            {t("Invite member")}
                        </ModalLink>
                    }
                    size="sm"
                />
            ) : null,
            description: t("Members see and record this team's activity together."),
            done: onboarding.has_member,
            key: "member",
            title: t("Invite a member"),
        },
    ];

    /**
     * How many steps the team already took.
     */
    const done = steps.filter((step) => step.done).length;

    return (
        <div className="overflow-hidden rounded-sm border">
            <div className="flex flex-wrap items-start justify-between gap-4 border-b p-4">
                <div className="flex flex-col gap-1">
                    <h2 className="font-medium">{t("Set up :team", { team: team.name })}</h2>
                    <p className="text-muted-foreground text-sm">{t("Three steps to get going.")}</p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground text-sm tabular-nums">
                        {t(":done of :total done", { done, total: steps.length })}
                    </span>
                    <Button onClick={onDismiss} size="sm" variant="ghost">
                        {t("Dismiss")}
                    </Button>
                </div>
            </div>
            <ItemGroup className="gap-0">
                {steps.map((step) => (
                    <Item
                        className="rounded-none border-0 border-b last:border-b-0"
                        key={step.key}
                        size="sm"
                        variant="outline"
                    >
                        <ItemMedia variant="icon">
                            {step.done ? <CheckIcon className="text-success" /> : <CircleDashedIcon />}
                        </ItemMedia>
                        <ItemContent>
                            <ItemTitle>{step.title}</ItemTitle>
                            <ItemDescription>{step.description}</ItemDescription>
                        </ItemContent>
                        {!step.done && step.action ? <ItemActions>{step.action}</ItemActions> : null}
                    </Item>
                ))}
            </ItemGroup>
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
        <div className="overflow-hidden rounded-sm border">
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <CircleDashedIcon />
                    </EmptyMedia>
                    <EmptyTitle>{t("No :name yet", { name: t("accounts") })}</EmptyTitle>
                    <EmptyDescription>{t("Add a new :name to get started.", { name: t("account") })}</EmptyDescription>
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

const EmptyTransactions = ({ team }: { team: string }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <div className="overflow-hidden rounded-sm border">
            <Empty>
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <CircleDashedIcon />
                    </EmptyMedia>
                    <EmptyTitle>{t("No :name yet", { name: t("transactions") })}</EmptyTitle>
                    <EmptyDescription>
                        {t("Add a new :name to get started.", { name: t("transaction") })}
                    </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <div className="flex flex-wrap justify-center gap-2">
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={transactions.create.url({ team, type: "income" })}>
                                    <ArrowDownIcon data-icon="inline-start" />
                                    {t("Register an :name", { name: t("income") })}
                                </ModalLink>
                            }
                            variant="outline"
                        />
                        <Button
                            nativeButton={false}
                            render={
                                <ModalLink href={transactions.create.url({ team, type: "expense" })}>
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

const SkeletonStrip = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
            <div className="flex divide-x">
                {Array.from({ length: 3 }).map((_, index) => (
                    <div className="flex flex-1 flex-col gap-2 p-4" key={index}>
                        <Skeleton className="h-4 w-12" />
                        <Skeleton className="h-7 w-28" />
                    </div>
                ))}
            </div>
        </div>
    );
};

const SkeletonActivity = () => {
    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-baseline justify-between gap-4">
                <Skeleton className="h-5 w-36" />
                <Skeleton className="h-7 w-24" />
            </div>
            <Item className="p-0 pb-4">
                <ItemHeader>
                    <Skeleton className="h-4 w-32" />
                </ItemHeader>
            </Item>
            <div className="min-w-0 overflow-x-auto rounded-sm border">
                <TableComponent className={TRANSACTION_TABLE_CLASS}>
                    <TableBody>
                        {[0, 1, 2].map((row) => (
                            <TableRow key={row}>
                                <TableCell className={TRANSACTION_COLUMNS.time}>
                                    <Skeleton className="h-4 w-10" />
                                </TableCell>
                                <TableCell className={TRANSACTION_COLUMNS.account}>
                                    <Skeleton className="h-4 w-32" />
                                </TableCell>
                                <TableCell className={TRANSACTION_COLUMNS.description}>
                                    <Skeleton className="h-4 w-48" />
                                </TableCell>
                                <TableCell className={cn("text-right", TRANSACTION_COLUMNS.amount)}>
                                    <Skeleton className="ml-auto h-4 w-20" />
                                </TableCell>
                                <TableCell className={TRANSACTION_COLUMNS.actions} />
                            </TableRow>
                        ))}
                    </TableBody>
                </TableComponent>
            </div>
        </div>
    );
};

Dashboard.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Dashboard;
