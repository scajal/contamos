import { Deferred, InfiniteScroll, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    EyeIcon,
    MoreHorizontalIcon,
    SearchIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Item, ItemHeader, ItemTitle } from "@/components/ui/item";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount } from "@/lib/utils";
import routes from "@/routes/finance/teams/transactions";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    transactions: LengthAwarePaginator<Transaction>;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Filters and actions */}
                <div className="flex items-center justify-between">
                    <div className="flex-2">
                        <Filters />
                    </div>
                    <div className="flex flex-1 justify-end">
                        <Actions />
                    </div>
                </div>

                {/** Transactions */}
                <div className="flex flex-col">
                    <Table />
                </div>
            </div>
        </div>
    );
};

const Header = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <header>
            <h1 className="text-2xl font-medium">{t("Transactions")}</h1>
        </header>
    );
};

const Filters = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <InputGroup>
            <InputGroupAddon>
                <SearchIcon />
            </InputGroupAddon>
            <InputGroupInput placeholder={t("Search by description")} />
        </InputGroup>
    );
};

const Actions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    return <Fragment></Fragment>;
};

const Table = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { transactions } = usePage<PageProps>().props;

    /**
     * Grouped transactions by date.
     */
    const grouped = Map.groupBy(transactions?.data ?? [], (transaction) =>
        format(new Date(transaction.occurred_at), "yyyy-MM-dd"),
    );

    return (
        <Fragment>
            <Deferred data="transactions" fallback={<></>}>
                <InfiniteScroll data="transactions" onlyNext>
                    {/** Empty state */}
                    {transactions?.data?.length < 1 && (
                        <div className="overflow-hidden rounded-sm border">
                            <TableComponent>
                                <TableBody>
                                    <TableRow>
                                        <TableCell colSpan={2}>
                                            <Empty>
                                                <EmptyHeader>
                                                    <EmptyMedia variant="icon">
                                                        <CircleDashedIcon />
                                                    </EmptyMedia>
                                                    <EmptyTitle>
                                                        {t("No :name yet", { name: t("transactions") })}
                                                    </EmptyTitle>
                                                    <EmptyDescription>
                                                        {t("Add a new :name to get started.", {
                                                            name: t("transaction"),
                                                        })}
                                                    </EmptyDescription>
                                                </EmptyHeader>
                                            </Empty>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </TableComponent>
                        </div>
                    )}
                    {/** Data */}
                    {[...grouped].map(([date, items]) => (
                        <Fragment key={date}>
                            <Item className="px-0">
                                <ItemHeader>
                                    <ItemTitle>{format(new Date(date), "MMMM d, yyyy")}</ItemTitle>
                                </ItemHeader>
                            </Item>
                            <div className="overflow-hidden rounded-sm border">
                                <TableComponent className="table-fixed">
                                    <TableBody>
                                        {items.map((transaction) => (
                                            <TableRow key={transaction.id}>
                                                <TableCell className="w-16">
                                                    {format(new Date(transaction.occurred_at), "HH:mm")}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="text-sm text-gray-500">
                                                            {transaction.account?.name ?? "-"}
                                                        </span>
                                                        <span className="font-medium">
                                                            {transaction.description ?? "-"}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <span
                                                        className={cn(
                                                            "font-medium",
                                                            transaction.type === "income"
                                                                ? "text-green-500"
                                                                : "text-red-500",
                                                        )}
                                                    >
                                                        {`${transaction.type === "income" ? "+" : "-"} ${formatAmount(transaction.amount, currentLocale(), transaction.currency)}`}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="w-12 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            render={<Button size="icon" variant="ghost" />}
                                                        >
                                                            <MoreHorizontalIcon />
                                                            <span className="sr-only">{t("Actions")}</span>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                render={
                                                                    <ModalLink
                                                                        href={routes.show.url({
                                                                            team: transaction.team_id,
                                                                            transaction: transaction.id,
                                                                        })}
                                                                    >
                                                                        <EyeIcon />
                                                                        <span>{t("View")}</span>
                                                                    </ModalLink>
                                                                }
                                                            />
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </TableComponent>
                            </div>
                        </Fragment>
                    ))}
                </InfiniteScroll>
            </Deferred>
        </Fragment>
    );
};

const Pagination = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { transactions } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!transactions?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={transactions?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!transactions?.next_page_url}
                        nativeButton={false}
                        render={<Link href={transactions?.next_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <span>{t("Next")}</span>
                        <ChevronRightIcon />
                    </Button>
                </PaginationItem>
            </PaginationContent>
        </PaginationComponent>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
