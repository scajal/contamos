import { Deferred, InfiniteScroll, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format, parse } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowDownIcon,
    ArrowLeftRightIcon,
    ArrowUpIcon,
    ChevronDownIcon,
    CircleDashedIcon,
    DownloadIcon,
    EyeIcon,
    HistoryIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    PencilIcon,
    RepeatIcon,
    SearchIcon,
    SearchXIcon,
    TrashIcon,
    XIcon,
} from "lucide-react";
import { useState } from "react";
import { type DateRange } from "react-day-picker";
import { Fragment } from "react/jsx-runtime";

import { CategoryIcon } from "@/components/category-icon";
import { ConfirmAction } from "@/components/confirm-action";
import { TransactionAmount, TransactionAuthor, TransactionRow as MovementRow } from "@/components/transaction-row";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import {
    Item,
    ItemActions,
    ItemContent,
    ItemDescription,
    ItemHeader,
    ItemMedia,
    ItemTitle,
} from "@/components/ui/item";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { useFilters } from "@/hooks/use-filters";
import { useSharedTeam } from "@/hooks/use-shared-team";
import { ApplicationLayout } from "@/layouts/application/layout";
import { CategoryOptions, flattenCategories } from "@/lib/categories";
import { TRANSACTION_COLUMNS, TRANSACTION_TABLE_CLASS, type TransactionTypeFilter } from "@/lib/transactions";
import { counterpartAccountName } from "@/lib/transfers";
import { cn, formatDate, formatDayHeading } from "@/lib/utils";
import subscriptions from "@/routes/billing/teams/subscriptions";
import routes from "@/routes/finance/teams/transactions";
import transferRoutes from "@/routes/finance/teams/transfers";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Category } from "@/types/app/modules/core/finance/enums/transaction/category";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { Tag } from "@/types/app/modules/core/finance/models/team/tag";
import { Transaction } from "@/types/app/modules/core/finance/models/team/transaction";
import { TransactionHistory } from "@/types/app/modules/core/finance/transaction-history";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    type: TransactionTypeFilter | null;
    category: Category | null;
    account: string | null;
    currency: string | null;
    tags: string | null;
    from: string | null;
    to: string | null;
};

type PageProps = {
    accounts: Account[];
    categories: CategoryOptions;
    currencies: Currency[];
    filters: Partial<PageFilters>;
    history: TransactionHistory;
    tags: Tag[];
    transactions: LengthAwarePaginator<Transaction>;
    types: TransactionTypeFilter[];
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
                    <div className="flex justify-end max-sm:w-full">
                        <ActionsMenu className="max-sm:w-full" />
                    </div>
                </div>

                {/** Transactions */}
                <Listing />

                {/** Where the plan's window ends, which is where the reader
                 * runs out of history rather than the top of the page. */}
                <HistoryNotice />
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
        <header className="flex flex-col gap-1">
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">
                {t("Transactions")}
            </h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Income and expenses recorded for this team.")}
            </p>
        </header>
    );
};

const HistoryNotice = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { history, user } = usePage<PageProps>().props;

    /**
     * A team whose plan keeps everything, or one with nothing
     * older to miss, is told nothing.
     */
    if (!history.limited || !history.has_older || !history.since) {
        return null;
    }

    const since = toDate(history.since);

    return (
        <Item className="mt-4" variant="muted">
            <ItemMedia variant="icon">
                <HistoryIcon className="text-muted-foreground" />
            </ItemMedia>
            <ItemContent>
                <ItemDescription>
                    {t(
                        "Showing transactions since :date. The Free plan keeps the last :months months visible. Nothing was removed, upgrade to Pro to see your full history.",
                        {
                            date: since ? formatDate(since, currentLocale()) : "",
                            months: String(history.months ?? ""),
                        },
                    )}
                </ItemDescription>
            </ItemContent>
            <ItemActions>
                <Button
                    nativeButton={false}
                    render={
                        <ModalLink
                            href={subscriptions.upgrade.url(
                                { team: user?.current_team?.slug ?? "" },
                                { query: { feature: "history:view" } },
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

const ActionsMenu = ({ className, variant = "outline" }: { className?: string; variant?: "default" | "outline" }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { filters, transactions, user } = usePage<PageProps>().props;

    /**
     * The current team's route key.
     */
    const team = user?.current_team?.slug ?? "";

    /**
     * The filters the listing is showing, so the export carries the
     * same query the page was rendered with.
     */
    const filter = Object.fromEntries(
        Object.entries(filters ?? {}).filter((entry): entry is [string, string] => !!entry[1]),
    );

    /**
     * Where the export downloads from, in the browser's own timezone
     * so the dates match what the list shows.
     */
    const exportUrl = routes.export.url(
        { team },
        { query: { filter, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone } },
    );

    /**
     * A listing that has not loaded, or loaded empty, has nothing
     * worth downloading.
     */
    const isExportable = !!transactions?.data?.length;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button className={className} variant={variant} />}>
                {t("Actions")}
                <ChevronDownIcon data-icon="inline-end" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        render={
                            <ModalLink href={routes.create.url({ team, type: "income" })}>
                                <ArrowDownIcon />
                                <span>{t("enums.income")}</span>
                            </ModalLink>
                        }
                    />
                    <DropdownMenuItem
                        render={
                            <ModalLink href={transferRoutes.create.url({ team })}>
                                <ArrowLeftRightIcon />
                                <span>{t("Transfer")}</span>
                            </ModalLink>
                        }
                    />
                    <DropdownMenuItem
                        render={
                            <ModalLink href={routes.create.url({ team, type: "expense" })}>
                                <ArrowUpIcon />
                                <span>{t("enums.expense")}</span>
                            </ModalLink>
                        }
                    />
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem
                        disabled={!isExportable}
                        render={isExportable ? <a href={exportUrl} /> : undefined}
                    >
                        <DownloadIcon />
                        <span>{t("Export CSV")}</span>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
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
    const {
        accounts,
        categories,
        currencies,
        filters: initial,
        history,
        tags,
        types,
        user,
    } = usePage<PageProps>().props;

    /**
     * The first day the plan still reaches, so the picker cannot
     * offer a range that would come back empty.
     */
    const since = toDate(history.since);

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
            type: initial?.type ?? null,
            category: initial?.category ?? null,
            account: initial?.account ?? null,
            currency: initial?.currency ?? null,
            tags: initial?.tags ?? null,
            from: initial?.from ?? null,
            to: initial?.to ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
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
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="transactions-search">
                    {t("Search")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="transactions-search"
                        onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                        placeholder={t("Search by ID or description")}
                        value={filters.search ?? ""}
                    />
                </InputGroup>
            </Field>

            {/** Advanced filters */}
            <Popover>
                <PopoverTrigger className="relative" render={<Button size="icon" variant="outline" />}>
                    <ListFilterPlusIcon />
                    <span className="sr-only">{t("More filters")}</span>
                    {isFiltered && <span className="bg-primary absolute -top-1 -right-1 size-3 rounded-full" />}
                </PopoverTrigger>
                <PopoverContent>
                    <PopoverHeader>
                        <PopoverTitle>{t("More filters")}</PopoverTitle>
                    </PopoverHeader>
                    <FieldGroup className="gap-4">
                        {/** Type */}
                        <Field>
                            <FieldLabel>{t("Type")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ type: value })} value={filters.type}>
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: TransactionTypeFilter | null) =>
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

                        {/** Category */}
                        <Field>
                            <FieldLabel>{t("Category")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ category: value })} value={filters.category}>
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: Category | null) => `${value ? t(`enums.${value}`) : t("All")}`}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {flattenCategories(categories).map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
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
                                <SelectTrigger className="w-full">
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
                            <Select onValueChange={(value) => setFilters({ currency: value })} value={filters.currency}>
                                <SelectTrigger className="w-full">
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

                        {/** Tags */}
                        <Field>
                            <FieldLabel>{t("Tags")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ tags: value })} value={filters.tags}>
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${tags.find((tag) => tag.id === value)?.name ?? t("All")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {tags.map((tag) => (
                                            <SelectItem key={tag.id} value={tag.id}>
                                                {tag.name}
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
                                disabled={since ? { before: since } : undefined}
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
                    </FieldGroup>
                </PopoverContent>
            </Popover>

            {/** Clear */}
            <Button disabled={!isFiltered} onClick={clear} size="icon" variant="outline">
                <XIcon />
                <span className="sr-only">{t("Clear filters")}</span>
            </Button>
        </div>
    );
};

const Listing = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Whether the team is shared, and so whether each row says who
     * recorded it.
     */
    const showsAuthor = useSharedTeam();

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
        <Deferred data="transactions" fallback={<SkeletonGroup />}>
            <div>
                <InfiniteScroll data="transactions" onlyNext>
                    {!transactions?.data?.length ? (
                        <div className="overflow-hidden rounded-xl border">
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
                                            : t("Add :name to get started.", {
                                                  name: t("transactions"),
                                              })}
                                    </EmptyDescription>
                                </EmptyHeader>
                                {!isFiltered ? (
                                    <EmptyContent>
                                        <ActionsMenu variant="default" />
                                    </EmptyContent>
                                ) : null}
                            </Empty>
                        </div>
                    ) : (
                        [...grouped].map(([date, items]) => (
                            <Fragment key={date}>
                                <Item className="p-0 pb-4 not-first:mt-4">
                                    <ItemHeader>
                                        <ItemTitle>
                                            {formatDayHeading(parse(date, DATE_FORMAT, new Date()), currentLocale(), {
                                                today: t("Today"),
                                                yesterday: t("Yesterday"),
                                            })}
                                        </ItemTitle>
                                    </ItemHeader>
                                </Item>
                                {/** A phone has no room for the table's columns, and the
                                 * amount is the point of the row, so it reads as a list. */}
                                <div className="min-w-0 rounded-xl border md:hidden">
                                    {items.map((transaction) => (
                                        <MovementRow
                                            actions={<TransactionActions transaction={transaction} />}
                                            key={transaction.id}
                                            showsAuthor={showsAuthor}
                                            transaction={transaction}
                                        />
                                    ))}
                                </div>
                                <div className="hidden min-w-0 overflow-x-auto rounded-xl border md:block">
                                    <TableComponent className={TRANSACTION_TABLE_CLASS}>
                                        <TableBody>
                                            {items.map((transaction) => (
                                                <TransactionRow key={transaction.id} transaction={transaction} />
                                            ))}
                                        </TableBody>
                                    </TableComponent>
                                </div>
                            </Fragment>
                        ))
                    )}
                </InfiniteScroll>
            </div>
        </Deferred>
    );
};

const TransactionRow = ({ transaction }: { transaction: Transaction }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * Whether the team is shared, and so whether the row says who
     * recorded the movement.
     */
    const showsAuthor = useSharedTeam();

    /**
     * The account on the other side, so a transfer row says where the
     * money went or came from.
     */
    const counterpart = transaction.transfer_id ? counterpartAccountName(transaction) : null;

    return (
        <TableRow>
            <TableCell className={cn("text-muted-foreground tabular-nums", TRANSACTION_COLUMNS.time)}>
                {new Intl.DateTimeFormat(currentLocale(), { hour: "2-digit", minute: "2-digit" }).format(
                    new Date(transaction.occurred_at),
                )}
            </TableCell>
            <TableCell className={TRANSACTION_COLUMNS.account}>
                <TruncatedText className="w-full" fallback="-" value={transaction.account?.name} />
            </TableCell>
            <TableCell className={TRANSACTION_COLUMNS.description}>
                <div className="flex min-w-0 items-center gap-1.5">
                    {transaction.category ? (
                        <CategoryIcon
                            aria-label={t(`enums.${transaction.category}`)}
                            className="text-muted-foreground size-3.5 shrink-0"
                            category={transaction.category}
                        />
                    ) : null}
                    {transaction.transfer_id ? (
                        <ArrowLeftRightIcon
                            aria-label={t("Transfer")}
                            className="text-muted-foreground size-3.5 shrink-0"
                        />
                    ) : null}
                    {transaction.recurring_rule_id ? (
                        <RepeatIcon aria-label={t("Recurring")} className="text-muted-foreground size-3.5 shrink-0" />
                    ) : null}
                    <TruncatedText
                        className="w-full"
                        fallback={counterpart ?? t(`enums.${transaction.type}`)}
                        value={counterpart ?? transaction.description}
                    />
                </div>
            </TableCell>
            <TableCell className={TRANSACTION_COLUMNS.tags}>
                <TagsCell tags={transaction.tags ?? []} />
            </TableCell>
            {showsAuthor ? (
                <TableCell className={TRANSACTION_COLUMNS.author}>
                    {transaction.user ? <TransactionAuthor user={transaction.user} /> : null}
                </TableCell>
            ) : null}
            <TableCell className={cn("text-right", TRANSACTION_COLUMNS.amount)}>
                <TransactionAmount transaction={transaction} />
            </TableCell>
            <TableCell className={cn("text-right", TRANSACTION_COLUMNS.actions)}>
                <TransactionActions transaction={transaction} />
            </TableCell>
        </TableRow>
    );
};

/**
 * The View, Edit, and Delete actions of a movement, shared by the table row
 * and the list row a phone shows instead of it. A row owned by a transfer
 * acts on the transfer, so it is never left half-recorded.
 */
const TransactionActions = ({ transaction }: { transaction: Transaction }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { user } = usePage().props;

    /**
     * The current team's route key.
     */
    const team = user?.current_team?.slug ?? "";

    /**
     * The transfer the row belongs to, when it is one of its legs or fees.
     */
    const transferId = transaction.transfer_id;

    /**
     * Where the row's View, Edit, and Delete actions lead.
     */
    const show = transferId
        ? transferRoutes.show.url({ team, transfer: transferId })
        : routes.show.url({ team, transaction: transaction.id });
    const edit = transferId
        ? transferRoutes.edit.url({ team, transfer: transferId })
        : routes.edit.url({ team, transaction: transaction.id });
    const destroy = transferId
        ? transferRoutes.destroy.url({ team, transfer: transferId })
        : routes.destroy.url({ team, transaction: transaction.id });

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
                                <ModalLink href={show}>
                                    <EyeIcon />
                                    <span>{t("View")}</span>
                                </ModalLink>
                            }
                        />
                        <DropdownMenuItem
                            render={
                                <ModalLink href={edit}>
                                    <PencilIcon />
                                    <span>{t("Edit")}</span>
                                </ModalLink>
                            }
                        />
                        <DropdownMenuItem onClick={() => setDeleteOpen(true)} variant="destructive">
                            <TrashIcon />
                            <span>{t("Delete")}</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmAction
                action={<Link href={destroy} method="delete" />}
                confirmLabel={t("Delete")}
                description={
                    <span className="flex flex-col gap-2">
                        {/** Naming the row is the point of the dialog: a
                         * movement cannot be brought back, so the reader
                         * has to recognise which one they are about to lose. */}
                        <span className="text-foreground flex flex-wrap items-baseline gap-x-2 gap-y-1 font-medium">
                            <TransactionAmount transaction={transaction} />
                            <span className="text-muted-foreground font-normal">
                                {[transaction.account?.name, formatDate(transaction.occurred_at, currentLocale())]
                                    .filter(Boolean)
                                    .join(" · ")}
                            </span>
                        </span>
                        <span>{t("This action cannot be undone.")}</span>
                    </span>
                }
                onOpenChange={setDeleteOpen}
                open={deleteOpen}
                title={
                    transferId
                        ? t("Are you sure you want to delete the transfer?")
                        : t("Are you sure you want to delete the transaction?")
                }
            />
        </Fragment>
    );
};

const SkeletonGroup = () => {
    /**
     * The placeholder mirrors the columns the loaded table will have,
     * so nothing shifts sideways when the rows arrive.
     */
    const showsAuthor = useSharedTeam();

    return (
        <div>
            <Item className="p-0 pb-4">
                <ItemHeader>
                    <Skeleton className="h-4 w-32" />
                </ItemHeader>
            </Item>
            <div className="min-w-0 overflow-x-auto rounded-xl border">
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
                                <TableCell className={TRANSACTION_COLUMNS.tags}>
                                    <Skeleton className="h-4 w-20" />
                                </TableCell>
                                {showsAuthor ? (
                                    <TableCell className={TRANSACTION_COLUMNS.author}>
                                        <Skeleton className="size-5 rounded-full" />
                                    </TableCell>
                                ) : null}
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

const TruncatedText = ({
    className,
    fallback = "",
    value,
}: {
    className?: string;
    fallback?: string;
    value?: string | null;
}) => {
    const text = value || fallback;

    if (!text) {
        return null;
    }

    return (
        <span className={cn("block truncate", className)} title={text}>
            {text}
        </span>
    );
};

const TagsCell = ({ tags }: { tags: Tag[] }) => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    if (tags.length === 0) {
        return null;
    }

    const [first, ...remaining] = tags;

    return (
        <div className="flex items-center gap-1">
            <Badge className="max-w-28" variant="outline">
                <span className="truncate" title={first.name}>
                    {first.name}
                </span>
            </Badge>
            {remaining.length > 0 ? (
                <Popover>
                    <PopoverTrigger
                        aria-label={t("Show :count more tags", { count: remaining.length })}
                        closeDelay={150}
                        delay={200}
                        openOnHover
                        render={<Button className="text-xs tabular-nums" size="icon-xs" variant="outline" />}
                    >
                        {`+${remaining.length}`}
                    </PopoverTrigger>
                    <PopoverContent align="start" className="w-auto max-w-64 gap-2 p-3">
                        <PopoverTitle className="sr-only">{t("Tags")}</PopoverTitle>
                        <div className="flex flex-wrap gap-1">
                            {remaining.map((tag) => (
                                <Badge key={tag.id} className="max-w-52" variant="outline">
                                    <span className="truncate" title={tag.name}>
                                        {tag.name}
                                    </span>
                                </Badge>
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            ) : null}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
