import { Deferred, usePage } from "@inertiajs/react";
import { format } from "date-fns";
import { useLaravelReactI18n } from "laravel-react-i18n";
import { CircleDashedIcon, MoreHorizontalIcon, PrinterIcon, ReceiptTextIcon } from "lucide-react";
import { Fragment } from "react/jsx-runtime";

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
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from "@/components/ui/item";
import { Skeleton } from "@/components/ui/skeleton";
import { ApplicationLayout } from "@/layouts/application/layout";
import { Transaction } from "@/types/app/modules/billing/models/transaction";
import { Team } from "@/types/app/modules/core/identity/models/team";
import { LengthAwarePaginator } from "@/types/illuminate/pagination/length-aware-paginator";

type PageProps = {
    plan: any;
    team: Team;
    transactions: LengthAwarePaginator<Transaction>;
};

const Index = () => {
    return (
        <div className="flex flex-col gap-8">
            {/** Header */}
            <Header />

            {/** Content */}
            <div className="flex flex-col gap-8">
                {/** Current plan */}
                {/** <Plan /> */}
                {/** Transactions */}
                <Transactions />
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
            <h1 className="text-2xl font-medium">{t("Billing")}</h1>
            <p className="text-muted-foreground text-sm">{t("Manage your team's billing and subscription.")}</p>
        </header>
    );
};

const Transactions = () => {
    /**
     * Translate the provided string
     * to the current locale.
     */
    const t = useLaravelReactI18n().t;

    /**
     * The page's props.
     */
    const { transactions } = usePage<PageProps>().props;

    return (
        <Fragment>
            <header className="flex flex-col gap-1">
                <h1 className="font-medium">{t("Transactions")}</h1>
                <p className="text-muted-foreground text-sm">{t("View your team's billing transactions.")}</p>
            </header>
            <Deferred data="transactions" fallback={<SkeletonRows />}>
                {!transactions?.data?.length ? (
                    <div className="rounded-sm border">
                        <Empty>
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <CircleDashedIcon />
                                </EmptyMedia>
                                <EmptyTitle>{t("No :name yet", { name: t("transactions") })}</EmptyTitle>
                            </EmptyHeader>
                        </Empty>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        <div className="overflow-hidden rounded-sm border">
                            <ItemGroup className="gap-0">
                                {(transactions?.data ?? []).map((transaction) => (
                                    <Item
                                        className="rounded-none border-0 border-b last:border-b-0"
                                        key={transaction.id}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <ItemMedia variant="icon">
                                            <ReceiptTextIcon />
                                        </ItemMedia>
                                        <ItemContent>
                                            <ItemTitle>{format(transaction.billed_at, "MMMM d, yyyy")}</ItemTitle>
                                            <ItemDescription>{`${transaction.currency} ${transaction.total}`}</ItemDescription>
                                        </ItemContent>
                                        <ItemActions className="flex-wrap justify-end">
                                            <Badge variant="ghost">{transaction.status}</Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger render={<Button size="icon" variant="ghost" />}>
                                                    <MoreHorizontalIcon />
                                                    <span className="sr-only">{t("Actions")}</span>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuGroup>
                                                        <DropdownMenuItem render={<a href="/" />}>
                                                            <PrinterIcon />
                                                            <span>{t("Download invoice")}</span>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuGroup>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </ItemActions>
                                    </Item>
                                ))}
                            </ItemGroup>
                        </div>
                        <PaginationControls
                            nextUrl={transactions?.next_page_url}
                            previousUrl={transactions?.prev_page_url}
                        />
                    </div>
                )}
            </Deferred>
        </Fragment>
    );
};

const SkeletonRows = () => {
    return (
        <div className="overflow-hidden rounded-sm border">
            {[0, 1, 2].map((row) => (
                <div className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0" key={row}>
                    <Skeleton className="size-8 rounded-sm" />
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
