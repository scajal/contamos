import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowLeftIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    ListFilterPlusIcon,
    MoreHorizontalIcon,
    PlusIcon,
    SearchIcon,
    SearchXIcon,
    TrashIcon,
    XIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { ConfirmAction } from "@/components/confirm-action";
import { BreadcrumbItem, BreadcrumbLink } from "@/components/ui/breadcrumb";
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
import { formatAmount } from "@/lib/utils";
import routes from "@/routes/administration/catalog/currencies";
import { QuoteSource } from "@/types/app/modules/core/catalog/enums/currency/quote/quote-source";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
    source: QuoteSource | null;
};

type PageProps = {
    currency: Currency;
    filters: Partial<PageFilters>;
    quotes: LengthAwarePaginator<Quote>;
    sources: Array<QuoteSource>;
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

                {/** Quotes */}
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

    /**
     * The page's props.
     */
    const { currency } = usePage<PageProps>().props;

    return (
        <header className="space-y-4">
            <Button className="-ml-3" render={<Link href={routes.index.url()} />} variant="ghost">
                <ArrowLeftIcon />
                {t("Back")}
            </Button>
            <h1 className="text-2xl font-medium">{t("Quotes of :currency", { currency: currency.name })}</h1>
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
    const { currency, filters: initial, sources } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
            source: initial?.source ?? null,
        },
        only: ["quotes", "filters"],
        url: (query) => routes.quotes.index.url({ currency: currency.id }, { query }),
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
                    placeholder={t("Search by date")}
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
                        {/** Source */}
                        <Field>
                            <FieldLabel>{t("Quote source")}</FieldLabel>
                            <Select onValueChange={(value) => setFilters({ source: value })} value={filters.source}>
                                <SelectTrigger>
                                    <SelectValue>
                                        {(value: QuoteSource | null) => `${value ? t(`enums.${value}`) : t("All")}`}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value={null}>{t("All")}</SelectItem>
                                        {sources.map((source) => (
                                            <SelectItem key={source} value={source}>
                                                {t(`enums.${source}`)}
                                            </SelectItem>
                                        ))}
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
    const { currency } = usePage<PageProps>().props;

    return (
        <Button
            className="max-sm:w-full"
            nativeButton={false}
            render={
                <ModalLink href={routes.quotes.create.url({ currency: currency.id })}>
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
    const { currentLocale, t } = useLaravelReactI18n();

    /**
     * The page's props.
     */
    const { currency, filters, quotes } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    <Deferred data="quotes" fallback={<SkeletonRows />}>
                        {/** Empty state */}
                        {quotes?.data?.length < 1 && (
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
                                                    : t("No :name yet", { name: t("quotes") })}
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                {isFiltered
                                                    ? t("Try adjusting or clearing the filters.")
                                                    : t("Add a new :name to get started.", { name: t("quote") })}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </TableCell>
                            </TableRow>
                        )}
                        {/** Data */}
                        {quotes?.data?.length
                            ? quotes?.data?.map((quote) => (
                                  <TableRow key={quote.id}>
                                      <TableCell>
                                          <div className="flex flex-col gap-1">
                                              <span className="font-medium">
                                                  {formatAmount(quote.rate, currentLocale(), currency)}
                                              </span>
                                              <span className="text-sm text-gray-500">
                                                  {format(new Date(quote.observed_at), "MMMM d, yyyy HH:mm")}
                                              </span>
                                          </div>
                                      </TableCell>
                                      <TableCell className="w-12 text-right">
                                          <DropdownMenu>
                                              <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                  <MoreHorizontalIcon />
                                                  <span className="sr-only">{t("Actions")}</span>
                                              </DropdownMenuTrigger>
                                              <DropdownMenuContent align="end">
                                                  <ConfirmAction
                                                      className="w-full"
                                                      title={t("Are you sure you want to delete the quote?")}
                                                      description={t("This action cannot be undone.")}
                                                      confirmLabel={t("Delete")}
                                                      action={
                                                          <Link
                                                              href={routes.quotes.destroy.url({
                                                                  currency: currency.id,
                                                                  quote: quote.id,
                                                              })}
                                                              method="delete"
                                                          />
                                                      }
                                                  >
                                                      <DropdownMenuItem closeOnClick={false} variant="destructive">
                                                          <TrashIcon />
                                                          <span>{t("Delete")}</span>
                                                      </DropdownMenuItem>
                                                  </ConfirmAction>
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
            {quotes?.data?.length ? <Pagination /> : null}
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
                            <Skeleton className="h-3 w-32" />
                        </div>
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
    const { quotes } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!quotes?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={quotes?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!quotes?.next_page_url}
                        nativeButton={false}
                        render={<Link href={quotes?.next_page_url ?? undefined} />}
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

const Breadcrumb = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Fragment>
            <BreadcrumbItem>
                <BreadcrumbLink render={<Link href={routes.index.url()} />}>
                    <span>{t("Currencies")}</span>
                </BreadcrumbLink>
            </BreadcrumbItem>
        </Fragment>
    );
};

Index.layout = (page: React.ReactNode) => <ApplicationLayout breadcrumb={<Breadcrumb />}>{page}</ApplicationLayout>;

export default Index;
