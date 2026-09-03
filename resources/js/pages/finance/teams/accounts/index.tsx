import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    SearchXIcon,
    XIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Field, FieldLabel } from "@/components/ui/field";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { Popover, PopoverContent, PopoverHeader, PopoverTitle, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/finance/teams/accounts";
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

                {/** Accounts */}
                <div className="flex flex-col gap-4 [&>div]:rounded-sm [&>div]:border">
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
            <h1 className="text-2xl font-medium">{t("Accounts")}</h1>
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
        url: (query) => routes.index.url({ team: { id: user?.current_team_id ?? "" } }, { query }),
        only: ["accounts", "filters"],
    });

    return (
        <div className="flex items-center gap-2">
            {/** Search */}
            <InputGroup>
                <InputGroupAddon>
                    <SearchIcon />
                </InputGroupAddon>
                <InputGroupInput
                    onChange={(event) => setFilters({ search: event.target.value }, { debounced: true })}
                    placeholder={t("Search by name")}
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
                <PopoverContent>
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
                                <SelectTrigger>
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
            className="max-sm:w-full"
            nativeButton={false}
            render={
                <ModalLink href={routes.create.url({ team: { id: user?.current_team_id ?? "" } })}>
                    <PlusIcon />
                    <span>{t("Create")}</span>
                </ModalLink>
            }
        />
    );
};

const Table = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { accounts, filters } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    <Deferred data="accounts" fallback={<SkeletonRows />}>
                        {/** Empty state */}
                        {accounts?.data?.length < 1 && (
                            <TableRow>
                                <TableCell colSpan={3}>
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
                                                    : t("Add a new :name to get started.", { name: t("account") })}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </TableCell>
                            </TableRow>
                        )}
                        {/** Data */}
                        {accounts?.data?.length
                            ? accounts?.data?.map((account) => (
                                  <TableRow key={account.id}>
                                      <TableCell>
                                          <div className="flex flex-col gap-1">
                                              <span className="font-medium">{account.name}</span>
                                              <span className="text-sm text-gray-500">
                                                  {t(`enums.${account.type}`)}
                                              </span>
                                          </div>
                                      </TableCell>
                                      <TableCell>
                                          {account.is_active ? (
                                              <Badge variant="ghost">{t("Active")}</Badge>
                                          ) : (
                                              <Badge variant="destructive">{t("Inactive")}</Badge>
                                          )}
                                      </TableCell>
                                      <TableCell className="text-right">
                                          <DropdownMenu>
                                              <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                  <MoreHorizontalIcon />
                                                  <span className="sr-only">{t("Actions")}</span>
                                              </DropdownMenuTrigger>
                                              <DropdownMenuContent align="end">
                                                  <DropdownMenuItem
                                                      render={
                                                          <ModalLink
                                                              href={routes.edit.url({
                                                                  account: { id: account.id },
                                                                  team: { id: account.team_id },
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
                                                                            team: account.team_id,
                                                                        })
                                                                      : routes.activate.url({
                                                                            account: account.id,
                                                                            team: account.team_id,
                                                                        })
                                                              }
                                                              method="patch"
                                                          >
                                                              {account.is_active ? <XIcon /> : <CheckIcon />}
                                                              <span>
                                                                  {account.is_active ? t("Deactivate") : t("Activate")}
                                                              </span>
                                                          </Link>
                                                      }
                                                  />
                                              </DropdownMenuContent>
                                          </DropdownMenu>
                                      </TableCell>
                                  </TableRow>
                              ))
                            : null}
                    </Deferred>
                </TableBody>
            </TableComponent>

            {/** Pagination */}
            {accounts?.data?.length ? <Pagination /> : null}
        </Fragment>
    );
};

const SkeletonRows = () => {
    return (
        <Fragment>
            {[0, 1, 2].map((row) => (
                <TableRow key={row}>
                    <TableCell>
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-4 w-40" />
                            <Skeleton className="h-3 w-12" />
                        </div>
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-5 w-16" />
                    </TableCell>
                    <TableCell />
                </TableRow>
            ))}
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
    const { accounts } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!accounts?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={accounts?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!accounts?.next_page_url}
                        nativeButton={false}
                        render={<Link href={accounts?.next_page_url ?? undefined} />}
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
