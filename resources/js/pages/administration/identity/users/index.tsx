import { Deferred, Link, usePage } from "@inertiajs/react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    ArrowUpRightFromSquareIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    SearchIcon,
    SearchXIcon,
    XIcon,
} from "lucide-react";
import { Fragment } from "react/jsx-runtime";

import { Avatar, AvatarImage } from "@/components/ui/avatar";
import { BreadcrumbItem, BreadcrumbLink } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { Skeleton } from "@/components/ui/skeleton";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { useFilters } from "@/hooks/use-filters";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/administration/identity/users";
import { User } from "@/types/app/modules/core/identity/models/user";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageFilters = {
    search: string | null;
};

type PageProps = {
    filters: Partial<PageFilters>;
    users: LengthAwarePaginator<User>;
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
            <h1 className="text-2xl font-medium">{t("Users")}</h1>
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
    const { filters: initial } = usePage<PageProps>().props;

    /**
     * The filters synced with the URL.
     */
    const { clear, filters, isFiltered, setFilters } = useFilters<PageFilters>({
        initial: {
            search: initial?.search ?? null,
        },
        only: ["users", "filters"],
        url: (query) => routes.index.url({ query }),
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
                    placeholder={t("Search by ID, email or name")}
                    value={filters.search ?? ""}
                />
            </InputGroup>

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

    return (
        <Button
            className="max-sm:w-full"
            nativeButton={false}
            render={<a href="https://dashboard.workos.com" target="_blank" />}
            variant="outline"
        >
            <Avatar size="sm">
                <AvatarImage src="https://images.workoscdn.com/brand/v2/favicon@256x256.png" />
            </Avatar>
            <span>WorkOS</span>
            <ArrowUpRightFromSquareIcon />
        </Button>
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
    const { filters, users } = usePage<PageProps>().props;

    /**
     * Whether the listing is being filtered.
     */
    const isFiltered = Object.values(filters ?? {}).some((value) => value !== null && value !== "");

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    <Deferred data="users" fallback={<SkeletonRows />}>
                        {/** Empty state */}
                        {users?.data?.length < 1 && (
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
                                                    : t("No :name yet", { name: t("users") })}
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                {isFiltered
                                                    ? t("Try adjusting or clearing the filters.")
                                                    : t("Add a new :name to get started.", { name: t("user") })}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </TableCell>
                            </TableRow>
                        )}
                        {/** Data */}
                        {users?.data?.length
                            ? users?.data?.map((user) => (
                                  <TableRow key={user.id}>
                                      <TableCell>
                                          <div className="flex items-center gap-2">
                                              <Avatar>
                                                  <AvatarImage src={user.avatar ?? undefined} />
                                              </Avatar>
                                              <div className="flex flex-col">
                                                  <span className="font-medium">{user.name}</span>
                                                  <span className="text-sm text-gray-500">{user.email}</span>
                                              </div>
                                          </div>
                                      </TableCell>
                                      <TableCell className="text-right">
                                          <DropdownMenu>
                                              <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                  <MoreHorizontalIcon />
                                                  <span className="sr-only">{t("Actions")}</span>
                                              </DropdownMenuTrigger>
                                              <DropdownMenuContent
                                                  align="end"
                                                  className="w-auto min-w-(--anchor-width)"
                                              >
                                                  <DropdownMenuGroup>
                                                      <DropdownMenuItem nativeButton={false} />
                                                  </DropdownMenuGroup>
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
            {users?.data?.length ? <Pagination /> : null}
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
    const { users } = usePage<PageProps>().props;

    return (
        <PaginationComponent>
            <PaginationContent>
                <PaginationItem>
                    <Button
                        disabled={!users?.prev_page_url}
                        nativeButton={false}
                        render={<Link href={users?.prev_page_url ?? undefined} />}
                        variant="ghost"
                    >
                        <ChevronLeftIcon />
                        <span>{t("Previous")}</span>
                    </Button>
                </PaginationItem>
                <PaginationItem>
                    <Button
                        disabled={!users?.next_page_url}
                        nativeButton={false}
                        render={<Link href={users?.next_page_url ?? undefined} />}
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
