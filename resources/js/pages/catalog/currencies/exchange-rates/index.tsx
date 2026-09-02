import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { ChevronLeftIcon, ChevronRightIcon, CircleDashedIcon, PlusIcon, SearchIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { BreadcrumbItem, BreadcrumbLink } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/catalog/currencies";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { ExchangeRate } from "@/types/app/modules/core/catalog/models/currency/exchange-rate";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    currency: Currency;
    exchangeRates: LengthAwarePaginator<ExchangeRate>;
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

                {/** Currencies */}
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
        <header>
            <h1 className="text-2xl font-medium">{t("Exchange rates for :currency", { currency: currency.name })}</h1>
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
            <InputGroupInput placeholder={t("Search by date")} />
        </InputGroup>
    );
};

const Actions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    return (
        <Button
            nativeButton={false}
            render={
                <ModalLink href={routes.create.url()}>
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
    const { exchangeRates } = usePage<PageProps>().props;

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    {/** Empty state */}
                    {exchangeRates?.data?.length < 1 && (
                        <TableRow>
                            <TableCell colSpan={2}>
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <CircleDashedIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>{t("No :name yet", { name: t("exchange rates") })}</EmptyTitle>
                                        <EmptyDescription>
                                            {t("Add a new :name to get started.", { name: t("exchange rate") })}
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            </TableCell>
                        </TableRow>
                    )}
                    {/** Data */}
                    {exchangeRates?.data?.length
                        ? exchangeRates?.data?.map((exchangeRate) => (
                              <TableRow key={exchangeRate.id}>
                                  <TableCell>
                                      <div className="flex flex-col gap-1">
                                          <span className="font-medium">{exchangeRate.observed_at}</span>
                                          <span className="text-sm text-gray-500">{exchangeRate.rate}</span>
                                      </div>
                                  </TableCell>
                              </TableRow>
                          ))
                        : null}
                </TableBody>
            </TableComponent>

            {/** Pagination */}
            {exchangeRates?.data?.length ? <Pagination /> : null}
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
    const { exchangeRates } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!exchangeRates?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={exchangeRates?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!exchangeRates?.next_page_url}
                        nativeButton={false}
                        render={<Link href={exchangeRates?.next_page_url ?? undefined} />}
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
