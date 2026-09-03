import { Deferred, Link, usePage } from "@inertiajs/react";
import { ModalLink } from "@inertiaui/modal-react";
import { useLaravelReactI18n } from "laravel-react-i18n";
import {
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    CircleDashedIcon,
    MoreHorizontalIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
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
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Pagination as PaginationComponent, PaginationContent, PaginationItem } from "@/components/ui/pagination";
import { TableBody, TableCell, Table as TableComponent, TableRow } from "@/components/ui/table";
import { ApplicationLayout } from "@/layouts/application/layout";
import routes from "@/routes/finance/teams/accounts";
import { Account } from "@/types/app/modules/core/finance/models/team/account";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    accounts: LengthAwarePaginator<Account>;
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

    return (
        <InputGroup>
            <InputGroupAddon>
                <SearchIcon />
            </InputGroupAddon>
            <InputGroupInput placeholder={t("Search by name")} />
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

    return (
        <Button
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
    const { accounts } = usePage<PageProps>().props;

    return (
        <Fragment>
            <TableComponent>
                <TableBody>
                    <Deferred data="accounts" fallback={<></>}>
                        {/** Empty state */}
                        {accounts?.data?.length < 1 && (
                            <TableRow>
                                <TableCell colSpan={2}>
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <CircleDashedIcon />
                                            </EmptyMedia>
                                            <EmptyTitle>{t("No :name yet", { name: t("accounts") })}</EmptyTitle>
                                            <EmptyDescription>
                                                {t("Add a new :name to get started.", { name: t("account") })}
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
