import { Deferred, InfiniteScroll, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format, parse } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CircleDashedIcon,
    EyeIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    SearchIcon,
    SearchXIcon,
    XIcon,
} from "lucide-react";
import { type DateRange } from "react-day-picker";
import { Fragment } from "react/jsx-runtime";

import { BreadcrumbItem, BreadcrumbLink } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Item, ItemHeader, ItemTitle } from "@/components/ui/item";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount } from "@/lib/utils";
import routes from "@/routes/finance/teams/transactions";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { TransactionType } from "@/types/app/modules/core/finance/enums/transaction/transaction-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    type: TransactionType | null;
    account: string | null;
    currency: string | null;
    from: string | null;
    to: string | null;
};

type PageProps = {
    accounts: Account[];
    currencies: Currency[];
    filters: Partial<PageFilters>;
    transactions: LengthAwarePaginator<Transaction>;
    types: TransactionType[];
};

/**
 * The date format used in the URL.
 */
const DATE_FORMAT = "yyyy-MM-dd";

/**
 * Parse a URL date into a Date, if any.
 */
const toDate = (value: string | null): Date | undefined => (value ? parse(value, DATE_FORMAT, new Date()) : undefined);

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-4">
                {/** Filters and actions */}
                <div className="flex items-center justify-between gap-2 max-sm:flex-col">
                    <div className="flex-1 max-sm:w-full">
                        <Filters />
                    </div>
                    <div className="flex justify-end max-sm:order-first max-sm:w-full">
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

    /**
     * The page's props.
     */
    const { accounts, currencies, filters: initial, types, user } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
            type: initial?.type ?? null,
            account: initial?.account ?? null,
            currency: initial?.currency ?? null,
            from: initial?.from ?? null,
            to: initial?.to ?? null,
        },
        url: (query) => routes.index.url({ team: { id: user?.current_team_id ?? "" } }, { query }),
        only: ["transactions", "filters"],
        reset: ["transactions"],
    });

    /**
     * The selected date range.
     */
    const range: DateRange = { from: toDate(filters.from), to: toDate(filters.to) };

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <InputGroup>
                <InputGroupAddon>
                    <SearchIcon />
                </InputGroupAddon>
                <InputGroupInput
                    onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                    placeholder={t("Search by ID or description")}
                    value={filters.search ?? ""}
                />
            </InputGroup>

            {/** Advanced filters */}
            <Popover>
                <PopoverTrigger className="relative" render={<Button size="icon" variant="outline" />}>
                    <ListFilterPlusIcon />
                    {isFiltered && (
                        <span className="bg-destructive/50 absolute -top-1 -right-1 size-3 rounded-full text-white" />
                    )}
                </PopoverTrigger>
                <PopoverContent className="w-fit">
                    <PopoverHeader>
                        <PopoverTitle>{t("More filters")}</PopoverTitle>
                    </PopoverHeader>
                    <div className="flex flex-col gap-4">
                        {/** Type */}
                        <Field>
                            <FieldLabel>{t("Type")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ type: value })} value={filters.type}>
                                <SelectTrigger>
                                    <SelectValue>
                                        {(value: TransactionType | null) =>
                                            `${value ? t(`enums.${value}`) : t("All")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {types.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {t(`enums.${type}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>

                        {/** Account */}
                        <Field>
                            <FieldLabel>{t("Account")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ account: value })} value={filters.account}>
                                <SelectTrigger>
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${accounts.find((account) => account.id === value)?.name ?? t("All")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id}>
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>

                        {/** Currency */}
                        <Field>
                            <FieldLabel>{t("Currency")}</FieldLabel>
                            <Select
                                onValueChange={(value) => setFilters({ currency: value })}
                                value={filters.currency}
                            >
                                <SelectTrigger>
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${currencies.find((currency) => currency.id === value)?.code ?? t("All")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {currencies.map((currency) => (
                                            <SelectItem key={currency.id} value={currency.id}>
                                                {`${currency.code} · ${currency.name}`}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>

                        {/** Date range */}
                        <Field>
                            <FieldLabel>{t("Date")}</FieldLabel>
                            <Calendar
                                defaultMonth={range.from}
                                mode="range"
                                onSelect={(selected) =>
                                    setFilters({
                                        from: selected?.from ? format(selected.from, DATE_FORMAT) : null,
                                        to: selected?.to ? format(selected.to, DATE_FORMAT) : null,
                                    })
                                }
                                selected={range}
                            />
                        </Field>
                    </div>
                </PopoverContent>
            </Popover>

            {/** Clear */}
            <Button disabled={!isFiltered} onClick={clear} size="icon" variant="outline">
                <XIcon />
            </Button>
        </div>
    );
};

const Actions = () => {
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
    const { filters, transactions } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    /**
     * Grouped transactions by date.
     */
    const grouped = Map.groupBy(transactions?.data ?? [], (transaction) =>
        format(new Date(transaction.occurred_at), "yyyy-MM-dd"),
    );

    return (
        <Fragment>
            <Deferred data="transactions" fallback={<SkeletonGroup />}>
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
                                                        {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                                                    </EmptyMedia>
                                                    <EmptyTitle>
                                                        {isFiltered
                                                            ? t("No results for the current filters")
                                                            : t("No :name yet", { name: t("transactions") })}
                                                    </EmptyTitle>
                                                    <EmptyDescription>
                                                        {isFiltered
                                                            ? t("Try adjusting or clearing the filters.")
                                                            : t("Add a new :name to get started.", {
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
                            <Item className="p-0 pb-4 not-first:mt-4">
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
                                                <TableCell>{transaction.account?.name ?? "-"}</TableCell>
                                                <TableCell>{transaction.description ?? ""}</TableCell>
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

const SkeletonGroup = () => {
    return (
        <Fragment>
            <Item className="p-0 pb-4">
                <ItemHeader>
                    <Skeleton className="h-4 w-32" />
                </ItemHeader>
            </Item>
            <div className="overflow-hidden rounded-sm border">
                <TableComponent className="table-fixed">
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
        </Fragment>
    );
};

const Breadcrumb = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { user } = usePage<PageProps>().props;

    return (
        <Fragment>
            <BreadcrumbItem>
                <BreadcrumbLink render={<Link href={routes.index.url({ team: user?.current_team_id ?? "" })} />}>
                    <span>{t("Transactions")}</span>
                </BreadcrumbLink>
            </BreadcrumbItem>
        </Fragment>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout breadcrumb={<Breadcrumb />}>{page}</ApplicationLayout>;

export default Index;
