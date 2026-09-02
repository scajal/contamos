import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowLeftIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    PlusIcon,
    SearchIcon,
    TrashIcon,
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
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import { formatAmount } from "@/lib/utils";
import routes from "@/routes/catalog/currencies";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { Quote } from "@/types/app/modules/core/catalog/models/currency/quote";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    currency: Currency;
    quotes: LengthAwarePaginator<Quote>;
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

    /**
     * The page's props.
     */
    const { currency } = usePage<PageProps>().props;

    return (
        <Button
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
    const { currency, quotes } = usePage<PageProps>().props;

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    {/** Empty state */}
                    {quotes?.data?.length < 1 && (
                        <TableRow>
                            <TableCell colSpan={2}>
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <CircleDashedIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>{t("No :name yet", { name: t("quotes") })}</EmptyTitle>
                                        <EmptyDescription>
                                            {t("Add a new :name to get started.", { name: t("quote") })}
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
                </TableBody>
            </TableComponent>

            {/** Pagination */}
            {quotes?.data?.length ? <Pagination /> : null}
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
