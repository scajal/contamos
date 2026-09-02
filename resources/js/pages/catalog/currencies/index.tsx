import { Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

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
import routes from "@/routes/catalog/currencies";
import { Currency } from "@/types/app/modules/core/catalog/models/currency";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    currencies: LengthAwarePaginator<Currency>;
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

    return (
        <header>
            <h1 className="text-2xl font-medium">{t("Currencies")}</h1>
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
            <InputGroupInput placeholder={t("Search by name or code")} />
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
    const { currencies } = usePage<PageProps>().props;

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    {/** Empty state */}
                    {currencies?.data?.length < 1 && (
                        <TableRow>
                            <TableCell colSpan={2}>
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyMedia variant="icon">
                                            <CircleDashedIcon />
                                        </EmptyMedia>
                                        <EmptyTitle>{t("No :name yet", { name: t("currencies") })}</EmptyTitle>
                                        <EmptyDescription>
                                            {t("Add a new :name to get started.", { name: t("currency") })}
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            </TableCell>
                        </TableRow>
                    )}
                    {/** Data */}
                    {currencies?.data?.length
                        ? currencies?.data?.map((currency) => (
                              <TableRow key={currency.id}>
                                  <TableCell>
                                      <div className="flex flex-col gap-1">
                                          <span className="font-medium">{currency.name}</span>
                                          <span className="text-sm text-gray-500">{currency.code}</span>
                                      </div>
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
                                                      <ModalLink href={routes.edit.url(currency.id)}>
                                                          <PencilIcon />
                                                          <span>{t("Edit")}</span>
                                                      </ModalLink>
                                                  }
                                              />
                                          </DropdownMenuContent>
                                      </DropdownMenu>
                                  </TableCell>
                              </TableRow>
                          ))
                        : null}
                </TableBody>
            </TableComponent>

            {/** Pagination */}
            {currencies?.data?.length ? <Pagination /> : null}
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
    const { currencies } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!currencies?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={currencies?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!currencies?.next_page_url}
                        nativeButton={false}
                        render={<Link href={currencies?.next_page_url ?? undefined} />}
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
