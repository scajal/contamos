import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    BanknoteIcon,
    BitcoinIcon,
    CheckIcon,
    CircleDashedIcon,
    LandmarkIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    SearchXIcon,
    XIcon,
    type LucideIcon,
} from "lucide-react";

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
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useBalances } from "@/hooks/use-balances";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import { cn, formatAmount } from "@/lib/utils";
import routes from "@/routes/finance/teams/accounts";
import transactions from "@/routes/finance/teams/transactions";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { AccountType } from "@/types/app/modules/core/finance/enums/account/account-type";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    type: AccountType | null;
    is_active: string | null;
    currency: string | null;
};

type PageProps = {
    accounts: LengthAwarePaginator<Account>;
    currencies: Currency[];
    filters: Partial<PageFilters>;
    types: AccountType[];
};

const TYPE_ICONS: Record<AccountType, LucideIcon> = {
    bank: LandmarkIcon,
    cash: BanknoteIcon,
    crypto: BitcoinIcon,
};

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
                        <CreateAccountButton className="max-sm:w-full" />
                    </div>
                </div>

                {/** Accounts */}
                <Listing />
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
            <h1 className="font-heading text-[1.75rem] leading-tight font-semibold tracking-tight">{t("Accounts")}</h1>
            <p className="text-muted-foreground text-[15px] leading-relaxed">
                {t("Bank, cash, and crypto accounts this team transacts with.")}
            </p>
        </header>
    );
};

const CreateAccountButton = ({ className }: { className?: string }) => {
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
                    {t("Create :name", { name: t("account") })}
                </ModalLink>
            }
        />
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
    const { currencies, filters: initial, types, user } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
            type: initial?.type ?? null,
            is_active: initial?.is_active ?? null,
            currency: initial?.currency ?? null,
        },
        url: (query) => routes.index.url({ team: user?.current_team?.slug ?? "" }, { query }),
        only: ["accounts", "filters"],
    });

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <Field className="flex-1">
                <FieldLabel className="sr-only" htmlFor="accounts-search">
                    {t("Search")}
                </FieldLabel>
                <InputGroup>
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="accounts-search"
                        onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                        placeholder={t("Search by name")}
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
                                        {(value: AccountType | null) => `${value ? t(`enums.${value}`) : t("All")}`}
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

                        {/** Status */}
                        <Field>
                            <FieldLabel>{t("Status")}</FieldLabel>
                            <Select
                                onValueChange={(value) => setFilters({ is_active: value })}
                                value={filters.is_active}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue>
                                        {(value: string | null) =>
                                            `${value === null ? t("All") : value === "true" ? t("Active") : t("Inactive")}`
                                        }
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        <SelectItem value="true">{t("Active")}</SelectItem>
                                        <SelectItem value="false">{t("Inactive")}</SelectItem>
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
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { accounts, filters, user } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Deferred data="accounts" fallback={<SkeletonRows />}>
            {!accounts?.data?.length ? (
                <div className="rounded-xl border">
                    <Empty>
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                {isFiltered ? <SearchXIcon /> : <CircleDashedIcon />}
                            </EmptyMedia>
                            <EmptyTitle>
                                {isFiltered
                                    ? t("No results for the current filters")
                                    : t("No :name yet", { name: t("accounts") })}
                            </EmptyTitle>
                            <EmptyDescription>
                                {isFiltered
                                    ? t("Try adjusting or clearing the filters.")
                                    : t("Add :name to get started.", { name: t("accounts") })}
                            </EmptyDescription>
                        </EmptyHeader>
                        {!isFiltered ? (
                            <EmptyContent>
                                <CreateAccountButton />
                            </EmptyContent>
                        ) : null}
                    </Empty>
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border">
                        <ItemGroup className="gap-0">
                            {(accounts?.data ?? []).map((account) => {
                                const Icon = TYPE_ICONS[account.type];

                                return (
                                    <Item
                                        className="rounded-none border-0 border-b last:border-b-0"
                                        key={account.id}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <ItemMedia variant="icon">
                                            <Icon />
                                        </ItemMedia>
                                        <ItemContent>
                                            <ItemTitle>{account.name}</ItemTitle>
                                            <ItemDescription>{t(`enums.${account.type}`)}</ItemDescription>
                                        </ItemContent>
                                        <AccountBalances account={account} team={user?.current_team?.slug ?? ""} />
                                        <ItemActions className="flex-wrap justify-end">
                                            {/** Active is the normal state, so only the exception is labelled. */}
                                            {account.is_active ? null : (
                                                <Badge variant="secondary">{t("Inactive")}</Badge>
                                            )}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                    <MoreHorizontalIcon />
                                                    <span className="sr-only">{t("Actions")}</span>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem
                                                            render={
                                                                <ModalLink
                                                                    href={routes.edit.url({
                                                                        account: { id: account.id },
                                                                        team: user?.current_team?.slug ?? "",
                                                                    })}
                                                                >
                                                                    <PencilIcon />
                                                                    <span>{t("Edit")}</span>
                                                                </ModalLink>
                                                            }
                                                        />
                                                        <DropdownMenuItem
                                                            nativeButton
                                                            render={
                                                                <Link
                                                                    className="w-full"
                                                                    href={
                                                                        account.is_active
                                                                            ? routes.deactivate.url({
                                                                                  account: account.id,
                                                                                  team: user?.current_team?.slug ?? "",
                                                                              })
                                                                            : routes.activate.url({
                                                                                  account: account.id,
                                                                                  team: user?.current_team?.slug ?? "",
                                                                              })
                                                                    }
                                                                    method="patch"
                                                                >
                                                                    {account.is_active ? <XIcon /> : <CheckIcon />}
                                                                    <span>
                                                                        {account.is_active
                                                                            ? t("Deactivate")
                                                                            : t("Activate")}
                                                                    </span>
                                                                </Link>
                                                            }
                                                        />
                                                    </DropdownMenuGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </ItemActions>
                                    </Item>
                                );
                            })}
                        </ItemGroup>
                    </div>
                    <PaginationControls nextUrl={accounts?.next_page_url} previousUrl={accounts?.prev_page_url} />
                </div>
            )}
        </Deferred>
    );
};

const AccountBalances = ({ account, team }: { account: Account; team: string }) => {
    /**
     * Get the current locale.
     */
    const currentLocale = useLaravelReactI18n().currentLocale;

    /**
     * Whether amounts are masked, and how to mask one.
     */
    const { hidden, mask } = useBalances();

    if (!account.balances?.length) {
        return null;
    }

    return (
        <div
            className={cn(
                "flex flex-col items-end gap-0.5 text-sm tabular-nums",
                // On a phone the stack drops under the account name.
                "max-sm:order-last max-sm:basis-full max-sm:items-start max-sm:pl-7.5",
            )}
        >
            {account.balances.map((balance) => {
                // Some drivers return aggregated decimals as numbers.
                const amount = String(balance.balance);
                const isNegative = amount.startsWith("-");
                const isZero = /^-?0*(\.0*)?$/.test(amount);

                return (
                    <Link
                        className={cn(
                            "rounded-sm whitespace-nowrap underline-offset-4 hover:underline",
                            // A masked amount keeps the neutral color, so the color never
                            // tells which accounts are negative or empty.
                            !hidden && !account.is_active && "text-muted-foreground",
                            !hidden && account.is_active && isZero && "text-muted-foreground",
                            !hidden && account.is_active && isNegative && !isZero && "text-destructive",
                        )}
                        href={transactions.index.url(
                            { team },
                            { query: { filter: { account: account.id, currency: balance.id } } },
                        )}
                        key={balance.id}
                        prefetch
                    >
                        {mask(`${isNegative && !isZero ? "- " : ""}${formatAmount(amount, currentLocale(), balance)}`)}{" "}
                        <span className="text-muted-foreground">{balance.code}</span>
                    </Link>
                );
            })}
        </div>
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
                        <Skeleton className="h-3 w-12" />
                    </div>
                    <Skeleton className="h-5 w-16" />
                </div>
            ))}
        </div>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout>{page}</ApplicationLayout>;

export default Index;
