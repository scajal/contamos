import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ArrowDownIcon, ArrowUpIcon, CircleDashedIcon, EyeIcon, PlusIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemHeader, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount } from "@/lib/utils";
import accounts from "@/routes/finance/teams/accounts";
import transactions from "@/routes/finance/teams/transactions";
import { AccountBalance } from "@/types/app/modules/core/finance/models/team/account-balance";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { Team } from "@/types/app/modules/core/identity/models/team";

type PageProps = {
    balances: Array<AccountBalance>;
    has_accounts: boolean;
    recent_transactions: Array<Transaction>;
    team: Team;
};

const Dashboard = () => {
    /**
     * The page's props.
     */
    const { has_accounts } = usePage<PageProps>().props;

    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

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
                <EmptyAccounts />
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
        <header className="flex flex-col gap-1">
            <h1 className="text-2xl font-medium">{team.name}</h1>
            <p className="text-muted-foreground text-sm">{t("Income and expenses across this team's accounts.")}</p>
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
                            <ItemTitle>{format(new Date(date), "MMMM d, yyyy")}</ItemTitle>
                        </ItemHeader>
                    </Item>
                    <div className="min-w-0 overflow-x-auto rounded-sm border">
                        <TableComponent>
                            <TableBody>
                                {items.map((transaction) => (
                                    <TableRow key={transaction.id}>
                                        <TableCell className="text-muted-foreground w-16 tabular-nums">
                                            {format(new Date(transaction.occurred_at), "HH:mm")}
                                        </TableCell>
                                        <TableCell>{transaction.account?.name ?? "-"}</TableCell>
                                        <TableCell>{transaction.description ?? ""}</TableCell>
                                        <TableCell className="text-right">
                                            <span
                                                className={cn(
                                                    "font-medium tabular-nums",
                                                    transaction.type === "expense"
                                                        ? "text-destructive"
                                                        : "text-success",
                                                )}
                                            >
                                                {`${transaction.type === "income" ? "+" : "-"} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`}
                                            </span>
                                        </TableCell>
                                        <TableCell className="w-12 text-right">
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
                <TableComponent>
                    <TableBody>
                        {[0, 1, 2].map((row) => (
                            <TableRow key={row}>
                                <TableCell className="w-16">
                                    <Skeleton className="h-4 w-10" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-4 w-32" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-4 w-48" />
                                </TableCell>
                                <TableCell className="text-right">
                                    <Skeleton className="ml-auto h-4 w-20" />
                                </TableCell>
                                <TableCell className="w-12" />
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
